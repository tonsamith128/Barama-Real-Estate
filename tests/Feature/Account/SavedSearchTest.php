<?php

declare(strict_types=1);

namespace Tests\Feature\Account;

use App\Models\SavedSearch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class SavedSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
    }

    public function test_guests_are_redirected_and_searches_are_customer_owned(): void
    {
        $this->get(route('account.searches.index'))->assertRedirect(route('login'));
        $this->post(route('account.searches.store'), ['name' => 'Hidden'])->assertRedirect(route('login'));

        $first = User::factory()->create();
        $second = User::factory()->create();
        $this->actingAs($first)->post(route('account.searches.store'), [
            'name' => 'Affordable homes', 'offer' => 'sale', 'max_price' => '180000', 'sort' => 'newest',
        ])->assertRedirect(route('account.searches.index'));

        $saved = SavedSearch::query()->firstOrFail();
        $this->assertSame($first->id, $saved->user_id);
        $this->assertSame('180000', $saved->filters['max_price']);

        $this->actingAs($second)->get(route('account.searches.index'))
            ->assertOk()->assertDontSee('Affordable homes');
        $this->patch(route('account.searches.rename', $saved), ['name' => 'Stolen'])->assertNotFound();
        $this->delete(route('account.searches.destroy', $saved))->assertNotFound();
        $this->assertDatabaseCount('saved_searches', 1);
    }

    public function test_customer_can_reopen_rename_and_delete_their_saved_search(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('account.searches.store'), [
            'name' => 'Rent a townhouse',
            'offer' => 'rent',
            'q' => 'Townhouse',
            'min_price' => '250',
            'max_price' => '2000',
            'sort' => 'price_asc',
        ])->assertRedirect(route('account.searches.index'));
        $search = SavedSearch::query()->firstOrFail();
        $page = $this->get(route('account.searches.index'))->assertOk()
            ->assertSee('Rent a townhouse')->assertSee('For rent');
        $page->assertSee(route('properties.index', $search->filters));
        $this->get(route('properties.index', $search->filters))->assertOk();

        $this->patch(route('account.searches.rename', $search), [
            'name' => 'My rental shortlist',
        ])->assertRedirect(route('account.searches.index'));
        $this->assertSame('My rental shortlist', $search->fresh()->name);
        $this->delete(route('account.searches.destroy', $search))
            ->assertRedirect(route('account.searches.index'));
        $this->assertDatabaseCount('saved_searches', 0);
    }

    public function test_saved_searches_are_visible_from_premium_customer_dashboard(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('account.searches.store'), [
            'name' => 'Villas in city',
            'type' => 'villa',
            'offer' => 'sale',
        ])->assertRedirect();
        $this->get(route('account.dashboard'))->assertOk()
            ->assertSee("Make room for what's next.")
            ->assertSee('Saved searches')
            ->assertSee('Villas in city');
        $this->get(route('account.searches.index'))->assertSee('YOUR PERSONAL COLLECTION');
    }

    public function test_browser_search_offers_price_controls_and_an_authenticated_save_form(): void
    {
        $this->get(route('properties.index'))->assertOk()
            ->assertSee('Min. price (USD)')->assertSee('Max. price (USD)')
            ->assertSee('Sign in to save this search');
        $user = User::factory()->create();
        $this->actingAs($user)->get(route('properties.index', [
            'offer' => 'rent', 'min_price' => 100, 'max_price' => 2000,
        ]))->assertOk()->assertSee('Save this search')
            ->assertSee('name="min_price"', false)
            ->assertSee('name="max_price"', false);
    }

    public function test_invalid_filters_price_ranges_or_unsafe_sort_values_are_rejected(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('account.searches.store'), [
            'name' => 'High budget', 'min_price' => '1000', 'max_price' => '900',
        ])->assertSessionHasErrors('max_price');
        $this->post(route('account.searches.store'), [
            'name' => 'Invalid transaction', 'offer' => 'sold',
        ])->assertSessionHasErrors('offer');
        $this->post(route('account.searches.store'), [
            'name' => 'Invalid ordering', 'sort' => 'SQL injection',
        ])->assertSessionHasErrors('sort');
        $this->assertDatabaseCount('saved_searches', 0);
    }

    public function test_maximum_twenty_searches_and_unique_names_are_enforced(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        for ($i = 1; $i <= 20; $i++) {
            $this->post(route('account.searches.store'), ['name' => 'Saved search '.$i])
                ->assertRedirect(route('account.searches.index'));
        }
        $this->post(route('account.searches.store'), ['name' => 'Beyond maximum'])
            ->assertSessionHasErrors('name');
        $this->assertDatabaseCount('saved_searches', 20);
        $first = SavedSearch::query()->firstOrFail();
        $this->delete(route('account.searches.destroy', $first))->assertRedirect();
        $this->post(route('account.searches.store'), ['name' => 'Saved search 2'])
            ->assertSessionHasErrors('name');
        $this->assertDatabaseCount('saved_searches', 19);
    }

    public function test_submitted_customer_html_is_escaped_in_saved_search_cards(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('account.searches.store'), [
            'name' => '<script>alert(1)</script> Search',
            'q' => '<img src=x onerror=alert(1)>',
        ])->assertRedirect();
        $this->get(route('account.searches.index'))->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;', false)
            ->assertDontSee('<img src=x', false);
    }

    public function test_unmigrated_account_dashboard_remains_accessible(): void
    {
        Schema::drop('saved_searches');
        $this->actingAs(User::factory()->create())->get(route('account.dashboard'))
            ->assertOk()->assertSee('Welcome back');
        $this->get(route('account.searches.index'))->assertStatus(409);
    }
}
