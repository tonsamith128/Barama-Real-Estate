<?php

declare(strict_types=1);

namespace Tests\Feature\Viewings;

use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\Listing;
use App\Models\Property;
use App\Models\User;
use App\Models\ViewingRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Tests\TestCase;

final class ViewingCalendarExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    private function viewing(User $user, string $slug, string $status = 'confirmed'): ViewingRequest
    {
        $company = Company::query()->create([
            'name' => 'Agency '.$slug, 'slug' => 'calendar-agency-'.$slug,
            'status' => 'approved', 'published_at' => now()->subDay(),
        ]);
        $property = Property::query()->create([
            'company_id' => $company->id, 'reference_code' => 'CAL-'.$slug,
            'title' => 'Calendar property '.$slug, 'property_type' => 'villa',
            'status' => 'approved',
        ]);
        $listing = Listing::query()->create([
            'property_id' => $property->id, 'slug' => 'calendar-'.$slug,
            'transaction_type' => 'sale', 'price' => '120000.00',
            'currency' => 'USD', 'status' => 'published',
            'published_at' => now()->subHour(),
        ]);

        return ViewingRequest::query()->create([
            'user_id' => $user->id, 'listing_id' => $listing->id,
            'requested_at' => now()->addDays(4), 'status' => $status,
        ]);
    }

    private function member(User $manager, ViewingRequest $viewing, string $role = 'manager'): CompanyMembership
    {
        return CompanyMembership::query()->create([
            'user_id' => $manager->id,
            'company_id' => $viewing->listing->property->company_id,
            'role' => $role, 'status' => 'active',
        ]);
    }

    public function test_customer_downloads_only_own_current_confirmed_appointment(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $confirmed = $this->viewing($owner, 'confirmed');
        $pending = $this->viewing($owner, 'pending', 'pending');

        $this->get(route('account.viewings.calendar', $confirmed))->assertRedirect();
        $response = $this->actingAs($owner)
            ->get(route('account.viewings.calendar', $confirmed));
        $response->assertOk();
        // Symfony may reorder Cache-Control directives; verify their meaning.
        foreach (['private', 'no-store', 'max-age=0'] as $directive) {
            $this->assertStringContainsString(
                $directive, $response->headers->get('Cache-Control', ''),
            );
        }
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $calendar = $response->getContent();
        $this->assertStringContainsString("BEGIN:VCALENDAR\r\n", $calendar);
        $this->assertStringContainsString("BEGIN:VEVENT\r\n", $calendar);
        $this->assertStringContainsString('SUMMARY:Viewing - Calendar property confirmed', $calendar);
        $this->assertStringContainsString("STATUS:CONFIRMED\r\n", $calendar);
        $this->assertStringNotContainsString('ATTENDEE:', $calendar);
        $this->assertStringNotContainsString('Please arrange', $calendar);

        $this->actingAs($other)->get(route('account.viewings.calendar', $confirmed))
            ->assertNotFound();
        $this->actingAs($owner)->get(route('account.viewings.calendar', $pending))
            ->assertNotFound();
    }

    public function test_held_historical_expired_or_unpublished_viewings_cannot_export(): void
    {
        $owner = User::factory()->create();
        $held = $this->viewing($owner, 'held');
        $held->forceFill(['review_status' => 'held'])->save();

        $historic = $this->viewing($owner, 'historic');
        $historic->forceFill([
            'legacy_uuid' => '11111111-1111-4111-8111-111111111111',
        ])->save();

        $past = $this->viewing($owner, 'past');
        $past->forceFill(['requested_at' => now()->subDay()])->save();

        $unpublished = $this->viewing($owner, 'unpublished');
        $unpublished->listing->forceFill(['status' => 'unpublished'])->save();

        foreach ([$held, $historic, $past, $unpublished] as $viewing) {
            $this->actingAs($owner)
                ->get(route('account.viewings.calendar', $viewing))
                ->assertNotFound();
        }
    }

    public function test_company_export_requires_active_manager_and_is_company_scoped(): void
    {
        $customer = User::factory()->create();
        $manager = User::factory()->create();
        $own = $this->viewing($customer, 'own');
        $other = $this->viewing($customer, 'other');
        $unconfirmed = $this->viewing($customer, 'unconfirmed', 'reschedule_proposed');
        $this->member($manager, $own);
        $this->member($manager, $unconfirmed);

        $guest = User::factory()->create();
        $this->actingAs($guest)->get(route('developer.viewings.calendar'))
            ->assertForbidden();

        $response = $this->actingAs($manager)->get(route('developer.viewings.calendar'));
        $response->assertOk();
        $content = $response->getContent();
        $this->assertStringContainsString('Calendar property own', $content);
        $this->assertStringNotContainsString('Calendar property other', $content);
        $this->assertStringNotContainsString('Calendar property unconfirmed', $content);
        $this->assertSame(1, substr_count($content, 'BEGIN:VEVENT'));

        $membership = CompanyMembership::query()
            ->where('user_id', $manager->id)
            ->where('company_id', $own->listing->property->company_id)
            ->firstOrFail();
        $membership->forceFill(['status' => 'inactive'])->save();
        $this->get(route('developer.viewings.calendar'))->assertOk();
        $this->assertSame(0, substr_count(
            $this->get(route('developer.viewings.calendar'))->getContent(),
            'BEGIN:VEVENT',
        ));
        $this->actingAs($guest)->get(route('developer.viewings.calendar'))
            ->assertForbidden();
    }

    public function test_unicode_line_folding_and_calendar_injection_are_escaped(): void
    {
        $owner = User::factory()->create();
        $viewing = $this->viewing($owner, 'unicode');
        $viewing->listing->property->forceFill([
            'title' => "Khmer ផ្ទះស្អាត, \r\nATTENDEE:mailto:attacker@example.test;".str_repeat('ផ្ទះ', 30),
        ])->save();

        $content = $this->actingAs($owner)
            ->get(route('account.viewings.calendar', $viewing))->getContent();

        $this->assertStringNotContainsString("\r\nATTENDEE:", $content);
        // RFC 5545 folds at 75 octets: unfold before checking escaped text.
        $unfolded = preg_replace('/\r\n[ \t]/', '', $content);
        $this->assertStringContainsString('\\nATTENDEE:mailto:attacker@example.test\\;', $unfolded);
        foreach (explode("\r\n", trim($content)) as $line) {
            $this->assertLessThanOrEqual(75, strlen($line));
        }
        $this->assertSame(1, substr_count($content, 'BEGIN:VEVENT'));
    }


    public function test_calendar_link_disappears_when_listing_is_withdrawn(): void
    {
        $owner = User::factory()->create();
        $confirmed = $this->viewing($owner, 'button');

        $this->actingAs($owner)->get(route('account.viewings.index'))
            ->assertOk()
            ->assertSee('Download calendar (.ics)')
            ->assertSee(route('account.viewings.calendar', $confirmed));

        $confirmed->listing->forceFill(['status' => 'unpublished'])->save();

        $this->get(route('account.viewings.index'))
            ->assertOk()->assertDontSee('Download calendar (.ics)');
        $this->get(route('account.viewings.calendar', $confirmed))->assertNotFound();
    }

    public function test_company_export_button_respects_membership_and_approval(): void
    {
        $customer = User::factory()->create();
        $manager = User::factory()->create();
        $viewing = $this->viewing($customer, 'membership');
        $membership = $this->member($manager, $viewing);

        $this->actingAs($manager)->get(route('developer.viewings.index'))
            ->assertOk()->assertSee('Export confirmed appointments (.ics)');

        $membership->forceFill(['status' => 'inactive'])->save();
        $this->get(route('developer.viewings.index'))
            ->assertOk()->assertDontSee('Export confirmed appointments (.ics)');
        $this->get(route('developer.viewings.calendar'))->assertForbidden();

        $membership->forceFill(['status' => 'active'])->save();
        $viewing->listing->property->company
            ->forceFill(['status' => 'pending'])->save();
        $this->get(route('developer.viewings.index'))
            ->assertOk()->assertDontSee('Export confirmed appointments (.ics)');
        $this->get(route('developer.viewings.calendar'))->assertForbidden();
    }

    public function test_company_calendar_caps_export_at_250_and_orders_same_time_consistently(): void
    {
        $customer = User::factory()->create();
        $manager = User::factory()->create();
        $first = $this->viewing($customer, 'limit');
        $this->member($manager, $first);

        $anchor = now()->addDays(2)->startOfMinute();
        $first->forceFill(['requested_at' => $anchor])->save();

        // All share one approved listing, so authorization and ordering,
        // not creation of extra catalog records, are under test.
        for ($i = 1; $i <= 250; $i++) {
            ViewingRequest::query()->create([
                'user_id' => $customer->id,
                'listing_id' => $first->listing_id,
                'requested_at' => $anchor->copy()->addMinutes($i),
                'status' => 'confirmed',
            ]);
        }

        $response = $this->actingAs($manager)
            ->get(route('developer.viewings.calendar'));
        $response->assertOk();
        $file = $response->getContent();
        $this->assertSame(250, substr_count($file, 'BEGIN:VEVENT'));
        $this->assertStringContainsString(
            'DTSTART:'.$anchor->copy()->utc()->format('Ymd\THis\Z'),
            $file,
        );
        $this->assertStringNotContainsString(
            'DTSTART:'.$anchor->copy()->addMinutes(250)->utc()->format('Ymd\THis\Z'),
            $file,
        );
        $this->assertSame(250, substr_count($file, 'STATUS:CONFIRMED'));
    }
}
