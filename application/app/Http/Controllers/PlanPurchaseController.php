<?php

declare(strict_types=1);

namespace Barama\Http\Controllers;

use Barama\Core\Request;
use Barama\Core\Response;
use Barama\Core\View;
use Barama\Repositories\FinanceRepository;
use Barama\Services\AuthService;
use Barama\Services\PlanPurchaseService;
use DomainException;
use InvalidArgumentException;
use Throwable;

final class PlanPurchaseController
{
    public function index(Request $request): Response
    {
        $plans = array_values(array_filter(
            (new FinanceRepository())->plans(),
            static fn (array $plan): bool =>
                (string) ($plan['status'] ?? '') === 'active'
        ));

        usort(
            $plans,
            static function (array $left, array $right): int {
                $sort = ((int) ($left['sort_order'] ?? 0))
                    <=> ((int) ($right['sort_order'] ?? 0));

                return $sort !== 0
                    ? $sort
                    : strcasecmp(
                        (string) ($left['name'] ?? ''),
                        (string) ($right['name'] ?? '')
                    );
            }
        );

        return Response::html(View::render('public/plans', [
            'pageTitle' => 'Plans & Packages',
            'metaDescription' =>
                'Compare active Barama Real Estate plans, pricing, and marketplace capacity.',
            'canonicalUrl' => url('/plans'),
            'metaRobots' => 'index,follow',
            'activeArea' => 'plans',
            'plans' => $plans,
        ]));
    }

    public function purchase(Request $request, string $id): Response
    {
        $plan = $this->activePlan($id);

        if ($plan === null) {
            return Response::html(
                View::render('errors/404', ['pageTitle' => 'Plan not found']),
                404
            );
        }

        $auth = new AuthService();
        $user = $auth->user();

        if ($user === null) {
            return Response::redirect(url('/login'));
        }

        return $this->purchaseView(
            $plan,
            (int) $user['id'],
            $request->input('requested') === '1'
                ? 'Your purchase request has been submitted for billing review. No entitlement is activated until payment is confirmed.'
                : null,
            null,
            200,
            trim((string) $request->input('reference', ''))
        );
    }

    public function submit(Request $request, string $id): Response
    {
        $plan = $this->activePlan($id);

        if ($plan === null) {
            return Response::html(
                View::render('errors/404', ['pageTitle' => 'Plan not found']),
                404
            );
        }

        $auth = new AuthService();
        $user = $auth->user();

        if ($user === null) {
            return Response::redirect(url('/login'));
        }

        $companyId = filter_var(
            $request->input('company_id'),
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        if ($companyId === false) {
            return $this->purchaseView(
                $plan,
                (int) $user['id'],
                null,
                'Choose the company that will use this plan.',
                422
            );
        }

        try {
            $record = (new PlanPurchaseService())->request(
                (int) $user['id'],
                (int) $plan['id'],
                (int) $companyId
            );

            return Response::redirect(
                url(
                    '/developer/plan-purchase/'
                    . (int) $plan['id']
                    . '?requested=1&reference='
                    . rawurlencode((string) $record['reference'])
                )
            );
        } catch (InvalidArgumentException|DomainException $exception) {
            return $this->purchaseView(
                $plan,
                (int) $user['id'],
                null,
                $exception->getMessage(),
                422
            );
        } catch (Throwable $exception) {
            return $this->purchaseView(
                $plan,
                (int) $user['id'],
                null,
                'The purchase request could not be created. Please try again or contact support.',
                500
            );
        }
    }

    /** @return array<string,mixed>|null */
    private function activePlan(string $id): ?array
    {
        $planId = filter_var(
            $id,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        if ($planId === false) {
            return null;
        }

        $plan = (new FinanceRepository())->plan((int) $planId);

        return $plan !== null
            && (string) ($plan['status'] ?? '') === 'active'
                ? $plan
                : null;
    }

    /** @param array<string,mixed> $plan */
    private function purchaseView(
        array $plan,
        int $userId,
        ?string $success,
        ?string $error,
        int $status,
        string $reference = ''
    ): Response {
        return Response::html(
            View::render('public/plan-purchase', [
                'pageTitle' => 'Purchase ' . (string) ($plan['name'] ?? 'Plan'),
                'metaDescription' =>
                    'Confirm the company and submit a Barama Real Estate plan purchase request.',
                'canonicalUrl' => url('/plans'),
                'metaRobots' => 'noindex,nofollow',
                'activeArea' => 'plans',
                'plan' => $plan,
                'companies' => (new FinanceRepository())->developerBillingCompanies($userId),
                'purchaseSuccess' => $success,
                'purchaseError' => $error,
                'purchaseReference' => $reference,
            ]),
            $status
        );
    }
}
