<?php

declare(strict_types=1);

namespace Tests\Feature\Marketplace;

use App\Models\Company;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PublicCompanyProjectTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_company_directory_excludes_unapproved_and_unpublished_firms(): void
    {
        $this->company('approved');
        $this->company('pending', ['status' => 'pending']);
        $this->company('future', ['published_at' => now()->addDay()]);
        $this->company('hidden', ['published_at' => null]);

        $this->get('/company')
            ->assertOk()
            ->assertSee('Company approved')
            ->assertDontSee('Company pending')
            ->assertDontSee('Company future')
            ->assertDontSee('Company hidden');

        $this->get('/company/pending')->assertNotFound();
        $this->get('/company/approved')->assertOk();
    }

    public function test_project_directory_requires_published_project_and_approved_company(): void
    {
        $approved = $this->company('approved');
        $suspended = $this->company('suspended', ['status' => 'suspended']);

        $this->project($approved, 'available');
        $this->project($approved, 'draft', ['status' => 'draft']);
        $this->project($approved, 'future', ['published_at' => now()->addDay()]);
        $this->project($suspended, 'blocked');

        $this->get('/projects')
            ->assertOk()
            ->assertSee('Project available')
            ->assertDontSee('Project draft')
            ->assertDontSee('Project future')
            ->assertDontSee('Project blocked');

        $this->get('/projects/available')->assertOk();
        $this->get('/projects/blocked')->assertNotFound();
    }

    private function company(string $slug, array $extra = []): Company
    {
        return Company::query()->create(array_merge([
            'name' => 'Company '.$slug,
            'slug' => $slug,
            'company_type' => 'developer',
            'status' => 'approved',
            'published_at' => now()->subDay(),
        ], $extra));
    }

    private function project(Company $company, string $slug, array $extra = []): Project
    {
        return Project::query()->create(array_merge([
            'company_id' => $company->getKey(),
            'name' => 'Project '.$slug,
            'slug' => $slug,
            'project_type' => 'residential',
            'status' => 'published',
            'published_at' => now()->subHour(),
        ], $extra));
    }
}
