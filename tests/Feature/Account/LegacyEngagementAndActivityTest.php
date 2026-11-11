<?php

declare(strict_types=1);

namespace Tests\Feature\Account;

use App\Models\Company;
use App\Models\ComparisonItem;
use App\Models\Listing;
use App\Models\Property;
use App\Models\RecentListingView;
use App\Models\SavedSearch;
use App\Models\User;
use App\Models\ViewingRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class LegacyEngagementAndActivityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    private function published(string $slug): Listing
    {
        $company = Company::query()->create([
            'name' => 'Engagement Agency '.$slug,
            'slug' => 'engage-company-'.$slug,
            'status' => 'approved', 'published_at' => now()->subDay(),
        ]);
        $property = Property::query()->create([
            'company_id' => $company->getKey(),
            'reference_code' => 'EG-'.$slug,
            'title' => 'Engagement Test '.$slug,
            'property_type' => 'villa',
            'status' => 'approved',
        ]);
        return Listing::query()->create([
            'property_id' => $property->getKey(),
            'slug' => 'engage-'.$slug,
            'transaction_type' => 'sale',
            'status' => 'published',
            'published_at' => now()->subHour(),
            'price' => '120000.00', 'currency' => 'USD',
        ]);
    }

    public function test_comparison_is_owner_scoped_limited_to_five_and_draft_protected(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $offers = [];
        for ($i = 0; $i < 6; $i++) {
            $offers[] = $this->published('compare-'.$i);
        }

        $this->actingAs($owner);
        foreach (array_slice($offers, 0, 5) as $offer) {
            $this->post(route('account.activity.comparison.store', $offer))
                ->assertRedirect(route('account.activity.index'));
        }
        $this->post(route('account.activity.comparison.store', $offers[0]))
            ->assertRedirect();
        $this->assertDatabaseCount('comparison_items', 5);
        $this->post(route('account.activity.comparison.store', $offers[5]))
            ->assertSessionHasErrors('comparison');

        $this->get(route('account.activity.index'))
            ->assertOk()->assertSee('Engagement Test compare-0');
        $this->actingAs($other)->get(route('account.activity.index'))
            ->assertOk()->assertDontSee('Engagement Test compare-0');
        $this->delete(route('account.activity.comparison.destroy', $offers[0]))
            ->assertRedirect();
        $this->assertDatabaseCount('comparison_items', 5);

        $offers[5]->update(['status' => 'draft']);
        $this->post(route('account.activity.comparison.store', $offers[5]))
            ->assertNotFound();
        $this->actingAs($owner)->delete(route('account.activity.comparison.destroy', $offers[0]))
            ->assertRedirect();
        $this->assertDatabaseCount('comparison_items', 4);
    }

    public function test_recent_history_is_opt_in_private_idempotently_counted_and_clearable(): void
    {
        $offer = $this->published('recent-history');
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($owner)->get(route('properties.show', $offer->slug))->assertOk();
        $this->assertDatabaseCount('recent_listing_views', 0);

        $this->post(route('account.activity.recent.store', $offer))->assertRedirect();
        $this->post(route('account.activity.recent.store', $offer))->assertRedirect();

        $row = RecentListingView::query()->firstOrFail();
        $this->assertSame(2, $row->view_count);
        $this->actingAs($other)->get(route('account.activity.index'))
            ->assertDontSee('Engagement Test recent-history');
        $this->delete(route('account.activity.recent.destroy', $offer))->assertRedirect();
        $this->assertDatabaseCount('recent_listing_views', 1);

        $this->actingAs($owner)->delete(route('account.activity.recent.clear'))
            ->assertRedirect();
        $this->assertDatabaseCount('recent_listing_views', 0);
    }

    public function test_imported_saved_search_is_not_actionable_without_explicit_owner_review(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $search = SavedSearch::query()->create([
            'user_id' => $owner->id,
            'name' => 'Historic Battambang Villas',
            'filters' => ['type' => 'villa', 'offer' => 'sale'],
        ]);
        $search->forceFill([
            'legacy_uuid' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
            'legacy_review_required' => true,
            'legacy_alerts_enabled' => false,
            'legacy_alert_frequency' => 'daily',
            'legacy_filter_evidence' => ['bedrooms_min' => '2.5', 'location_id' => 29],
        ])->save();

        $this->actingAs($owner)->get(route('account.searches.index'))->assertOk()
            ->assertSeeText('Review imported filters')->assertDontSee('View matches');
        $this->patch(route('account.searches.review', $search))
            ->assertSessionHasErrors('accept_supported_filters');

        $this->actingAs($other)->patch(route('account.searches.review', $search), [
            'accept_supported_filters' => '1',
        ])->assertNotFound();

        $this->actingAs($owner)->patch(route('account.searches.review', $search), [
            'accept_supported_filters' => '1',
        ])->assertRedirect(route('account.searches.index'));
        $this->assertFalse($search->fresh()->legacy_review_required);
        $this->assertFalse($search->fresh()->legacy_alerts_enabled);
        $this->assertSame('daily', $search->fresh()->legacy_alert_frequency);
        $this->assertSame('2.5', $search->fresh()->legacy_filter_evidence['bedrooms_min']);
        $this->get(route('account.searches.index'))->assertSee('View matches');
    }

    public function test_archived_viewings_show_source_date_without_creating_confirmed_appointments(): void
    {
        $offer = $this->published('historic-tour');
        $user = User::factory()->create();
        $viewing = ViewingRequest::query()->create([
            'user_id' => $user->getKey(),
            'listing_id' => $offer->getKey(),
            'requested_at' => '2026-10-18 00:00:00',
            'status' => 'legacy_review',
            'message' => 'Historic tour request',
        ]);
        $viewing->forceFill([
            'legacy_uuid' => 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
            'legacy_preferred_date' => '2026-10-18',
            'legacy_preferred_window' => 'morning',
            'legacy_original_status' => 'confirmed',
        ])->save();

        $this->actingAs($user)->get(route('account.viewings.index'))->assertOk()
            ->assertSeeText('Historical request')
            ->assertSeeText('Original status: Confirmed')
            ->assertSeeText('morning');
        $this->patch(route('account.viewings.cancel', $viewing))->assertStatus(409);
        $this->assertSame('legacy_review', $viewing->fresh()->status);
    }

    public function test_customer_history_rollback_guard_keeps_all_recorded_data(): void
    {
        $offer = $this->published('rollback');
        $user = User::factory()->create();
        ComparisonItem::query()->create([
            'user_id' => $user->id,
            'listing_id' => $offer->id,
            'position' => 0,
        ]);
        $migration = require database_path(
            'migrations/2026_11_11_000002_add_customer_engagement_legacy_parity.php'
        );

        try {
            $migration->down();
            $this->fail('Rollback must reject stored customer activity.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString(
                'Cannot remove historical customer engagement records',
                $e->getMessage()
            );
        }
        $this->assertTrue(Schema::hasTable('comparison_items'));
        $this->assertDatabaseCount('comparison_items', 1);
    }
}
