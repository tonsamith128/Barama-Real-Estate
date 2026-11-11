<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\AccountNotification;
use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\Listing;
use App\Models\Property;
use App\Models\User;
use App\Models\ViewingEvent;
use App\Models\ViewingRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class ViewingLifecycleAndArchivedCustomerReviewTest extends TestCase
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
        $company=Company::query()->create([
            'name'=>'QA Agency '.$slug,'slug'=>'view-agency-'.$slug,
            'status'=>'approved','published_at'=>now()->subDay(),
        ]);
        $property=Property::query()->create([
            'company_id'=>$company->id,'reference_code'=>'VR-'.$slug,
            'title'=>'QA Viewing Property '.$slug,'property_type'=>'villa',
            'status'=>'approved',
        ]);
        return Listing::query()->create([
            'property_id'=>$property->id,'slug'=>'view-'.$slug,
            'transaction_type'=>'sale','price'=>'125000.00',
            'currency'=>'USD','status'=>'published','published_at'=>now()->subHour(),
        ]);
    }

    private function membership(User $operator, Listing $listing, string $role='manager'): void
    {
        CompanyMembership::query()->create([
            'user_id'=>$operator->id,
            'company_id'=>$listing->property->company_id,
            'role'=>$role,'status'=>'active',
        ]);
    }

    private function requestViewing(User $customer, Listing $listing): ViewingRequest
    {
        $this->actingAs($customer)->post(route('account.viewings.store', $listing),[
            'requested_at'=>now()->addDays(5)->format('Y-m-d H:i:s'),
            'message'=>'A private test viewing.',
        ])->assertRedirect(route('account.viewings.index'));
        return ViewingRequest::query()->where('user_id',$customer->id)->firstOrFail();
    }

    public function test_company_proposes_and_customer_accepts_once_with_full_history(): void
    {
        $listing=$this->published('proposal');
        $customer=User::factory()->create();
        $manager=User::factory()->create();
        $other=User::factory()->create();
        $this->membership($manager,$listing);
        $viewing=$this->requestViewing($customer,$listing);

        $this->assertDatabaseHas('viewing_events',[
            'viewing_request_id'=>$viewing->id,'event_type'=>'submitted',
        ]);
        $proposal=now()->addDays(7)->format('Y-m-d H:i:s');
        $this->actingAs($other)->post(route('developer.viewings.propose',$viewing),[
            'proposed_at'=>$proposal,
        ])->assertNotFound();
        $this->actingAs($manager)->post(route('developer.viewings.propose',$viewing),[
            'proposed_at'=>$proposal,'reason'=>'The company has limited viewing slots.',
        ])->assertRedirect();

        $this->assertSame('reschedule_proposed',$viewing->fresh()->status);
        $this->assertNotNull($viewing->fresh()->proposed_expires_at);
        $this->actingAs($other)->post(route('account.viewings.respond',$viewing),[
            'decision'=>'accept',
        ])->assertNotFound();
        $this->actingAs($customer)->get(route('account.viewings.index'))
            ->assertOk()->assertSeeText('Accept proposed time');

        $this->post(route('account.viewings.respond',$viewing),[
            'decision'=>'accept',
        ])->assertRedirect();
        $this->assertSame('confirmed',$viewing->fresh()->status);
        $this->assertSame($proposal,$viewing->fresh()->requested_at->format('Y-m-d H:i:s'));
        $this->assertNull($viewing->fresh()->proposed_at);
        $this->assertDatabaseHas('viewing_events',[
            'viewing_request_id'=>$viewing->id,'event_type'=>'reschedule_accept',
            'actor_id'=>$customer->id,'actor_kind'=>'customer',
        ]);
        $this->post(route('account.viewings.respond',$viewing),[
            'decision'=>'accept',
        ])->assertStatus(409);
        $this->assertDatabaseCount('viewing_events',3);
        $this->assertGreaterThan(1,AccountNotification::query()->count());
    }

    public function test_company_approval_and_customer_cancellation_require_active_manager_role(): void
    {
        $listing=$this->published('cancel');
        $customer=User::factory()->create();
        $manager=User::factory()->create();
        $viewer=User::factory()->create();
        $this->membership($manager,$listing);
        $this->membership($viewer,$listing,'member');
        $viewing=$this->requestViewing($customer,$listing);

        $this->actingAs($viewer)->post(route('developer.viewings.decide',$viewing),[
            'decision'=>'confirmed',
        ])->assertNotFound();
        $this->actingAs($manager)->post(route('developer.viewings.decide',$viewing),[
            'decision'=>'confirmed',
        ])->assertRedirect();
        $this->assertSame('confirmed',$viewing->fresh()->status);

        $this->actingAs($customer)->patch(route('account.viewings.cancel',$viewing))
            ->assertRedirect();
        $this->assertSame('cancelled',$viewing->fresh()->status);
        $this->assertNotNull($viewing->fresh()->cancelled_at);
        $this->assertDatabaseHas('viewing_events',[
            'viewing_request_id'=>$viewing->id,'event_type'=>'cancelled',
            'actor_kind'=>'customer',
        ]);
        $this->actingAs($manager)->post(route('developer.viewings.cancel',$viewing))
            ->assertStatus(409);
    }

    public function test_expired_proposal_and_legacy_record_cannot_be_activated(): void
    {
        $listing=$this->published('archive');
        $customer=User::factory()->create();
        $manager=User::factory()->create();
        $this->membership($manager,$listing);
        $viewing=$this->requestViewing($customer,$listing);
        $this->actingAs($manager)->post(route('developer.viewings.propose',$viewing),[
            'proposed_at'=>now()->addDays(6)->format('Y-m-d H:i:s'),
        ])->assertRedirect();
        $viewing->forceFill(['proposed_expires_at'=>now()->subMinute()])->save();
        $this->actingAs($customer)->post(route('account.viewings.respond',$viewing),[
            'decision'=>'accept',
        ])->assertStatus(409);
        $this->assertSame('reschedule_proposed',$viewing->fresh()->status);

        $historical=ViewingRequest::query()->create([
            'user_id'=>$customer->id,'listing_id'=>$listing->id,
            'requested_at'=>now()->addDays(2),'status'=>'legacy_review',
        ]);
        $historical->forceFill([
            'legacy_uuid'=>'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
            'legacy_original_status'=>'confirmed',
        ])->save();
        $this->actingAs($manager)->post(route('developer.viewings.decide',$historical),[
            'decision'=>'confirmed',
        ])->assertStatus(409);
        $this->actingAs($customer)->patch(route('account.viewings.cancel',$historical))
            ->assertStatus(409);
        $this->assertSame('legacy_review',$historical->fresh()->status);
    }

    public function test_administrator_hold_blocks_company_decisions_and_release_is_audited(): void
    {
        $listing=$this->published('moderation');
        $customer=User::factory()->create();
        $manager=User::factory()->create();
        $admin=User::factory()->create(['system_role'=>'super_admin']);
        $this->membership($manager,$listing);
        $viewing=$this->requestViewing($customer,$listing);
        $this->actingAs($manager)->post(route('admin.viewings.review',$viewing),[
            'decision'=>'hold','reason'=>'Review required',
        ])->assertForbidden();
        $this->actingAs($admin)->post(route('admin.viewings.review',$viewing),[
            'decision'=>'hold','reason'=>'Investigate report',
        ])->assertRedirect();
        $this->assertSame('held',$viewing->fresh()->review_status);
        $this->actingAs($manager)->post(route('developer.viewings.decide',$viewing),[
            'decision'=>'confirmed',
        ])->assertStatus(409);
        $this->actingAs($admin)->get(route('admin.viewings.index',[
            'status'=>'pending','review'=>'held',
        ]))->assertOk()->assertSee('QA Viewing Property moderation');
        $this->post(route('admin.viewings.review',$viewing),[
            'decision'=>'release','reason'=>'Investigation finished',
        ])->assertRedirect();
        $this->assertSame('clear',$viewing->fresh()->review_status);
        $this->assertDatabaseHas('viewing_events',[
            'event_type'=>'admin_review_hold','actor_id'=>$admin->id,
        ]);
        $this->assertDatabaseHas('viewing_events',[
            'event_type'=>'admin_review_release','actor_id'=>$admin->id,
        ]);
        $this->actingAs($manager)->post(route('developer.viewings.decide',$viewing),[
            'decision'=>'confirmed',
        ])->assertRedirect();
        $this->assertSame('confirmed',$viewing->fresh()->status);
    }

    public function test_private_historical_lead_and_company_only_inquiry_review_is_admin_scoped(): void
    {
        $listing=$this->published('historic-lead');
        $company=$listing->property->company;
        $admin=User::factory()->create(['system_role'=>'super_admin']);
        $other=User::factory()->create();
        $batch=DB::table('legacy_import_batches')->insertGetId([
            'batch_uuid'=>'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee',
            'source_schema_label'=>'isolated_legacy_clone',
            'status'=>'staged_needs_review','mapped_records'=>2,
            'started_at'=>now(),'created_at'=>now(),'updated_at'=>now(),
        ]);
        $inquiry=DB::table('legacy_customer_inquiries')->insertGetId([
            'batch_id'=>$batch,'source_uuid'=>'11111111-1111-4111-8111-111111111111',
            'company_id'=>$company->id,
            'contact_name'=>'Private historical customer',
            'source'=>'website','source_status'=>'new',
            'message'=>'Confidential company-only question',
            'created_at'=>now(),'updated_at'=>now(),
        ]);
        $lead=DB::table('legacy_customer_leads')->insertGetId([
            'batch_id'=>$batch,'source_uuid'=>'22222222-2222-4222-8222-222222222222',
            'company_id'=>$company->id,'inquiry_id'=>$inquiry,
            'contact_name'=>'Private historical customer',
            'source'=>'inquiry','source_stage'=>'new',
            'currency'=>'USD','created_at'=>now(),'updated_at'=>now(),
        ]);
        $this->actingAs($other)->get(route('admin.customer-recovery.index'))
            ->assertForbidden();
        $this->post(route('admin.customer-recovery.inquiries.review',$inquiry),[
            'decision'=>'reviewed','note'=>'Verified offline only',
        ])->assertForbidden();
        $this->actingAs($admin)->get(route('admin.customer-recovery.index'))
            ->assertOk()->assertSee('Private historical customer');
        $this->post(route('admin.customer-recovery.inquiries.review',$inquiry),[
            'decision'=>'needs_followup','note'=>'Requires owner authorization',
        ])->assertRedirect();
        $this->post(route('admin.customer-recovery.leads.review',$lead),[
            'decision'=>'reviewed','note'=>'Archive reconciled offline',
        ])->assertRedirect();
        $this->assertDatabaseHas('legacy_customer_inquiries',[
            'id'=>$inquiry,'review_status'=>'needs_followup',
            'reviewed_by'=>$admin->id,
        ]);
        $this->assertDatabaseHas('legacy_customer_leads',[
            'id'=>$lead,'review_status'=>'reviewed',
            'reviewed_by'=>$admin->id,
        ]);
        $this->assertDatabaseCount('inquiries',0);
        $this->assertDatabaseCount('viewing_events',0);
        $this->assertDatabaseCount('account_notifications',0);
        $this->assertDatabaseHas('audit_events',[
            'subject_type'=>'legacy_customer_lead',
            'action'=>'legacy.customer.review_reviewed',
        ]);
    }

    public function test_unpublished_offer_cannot_be_confirmed_or_rescheduled(): void
    {
        $listing=$this->published('unpublished-offer');
        $customer=User::factory()->create();
        $manager=User::factory()->create();
        $this->membership($manager,$listing);
        $viewing=$this->requestViewing($customer,$listing);
        $listing->update(['status'=>'draft']);
        $this->actingAs($manager)->post(route('developer.viewings.decide',$viewing),[
            'decision'=>'confirmed',
        ])->assertSessionHasErrors('decision');
        $this->post(route('developer.viewings.propose',$viewing),[
            'proposed_at'=>now()->addDays(7)->format('Y-m-d H:i:s'),
        ])->assertSessionHasErrors('decision');
        $this->assertSame('pending',$viewing->fresh()->status);
        $this->assertDatabaseCount('viewing_events',1);
        $this->actingAs($customer)->patch(route('account.viewings.cancel',$viewing))
            ->assertRedirect();
        $this->assertSame('cancelled',$viewing->fresh()->status);
    }

    public function test_revoked_company_role_cannot_change_an_existing_viewing(): void
    {
        $listing=$this->published('role-revoked');
        $customer=User::factory()->create();
        $manager=User::factory()->create();
        $this->membership($manager,$listing);
        $viewing=$this->requestViewing($customer,$listing);
        $this->actingAs($manager)->get(route('developer.viewings.index'))
            ->assertOk()->assertSee('QA Viewing Property role-revoked');

        CompanyMembership::query()->where('user_id',$manager->id)->update(['status'=>'inactive']);
        $this->post(route('developer.viewings.propose',$viewing),[
            'proposed_at'=>now()->addDays(7)->format('Y-m-d H:i:s'),
        ])->assertNotFound();
        $this->assertSame('pending',$viewing->fresh()->status);
        $this->assertDatabaseCount('viewing_events',1);
    }

    public function test_admin_may_review_historical_appointment_without_activating_it(): void
    {
        $listing=$this->published('legacy-review');
        $customer=User::factory()->create();
        $admin=User::factory()->create(['system_role'=>'super_admin']);
        $viewing=ViewingRequest::query()->create([
            'user_id'=>$customer->id,'listing_id'=>$listing->id,
            'requested_at'=>now()->subMonth(),'status'=>'legacy_review',
        ]);
        $viewing->forceFill([
            'legacy_uuid'=>'eeeeeeee-eeee-4eee-8eee-eeeeeeeeeeee',
            'legacy_original_status'=>'confirmed',
        ])->save();
        $this->actingAs($admin)->post(route('admin.viewings.review',$viewing),[
            'decision'=>'hold','reason'=>'Check original customer identity',
        ])->assertRedirect();
        $this->assertSame('held',$viewing->fresh()->review_status);
        $this->post(route('admin.viewings.review',$viewing),[
            'decision'=>'release','reason'=>'Historical source reconciled',
        ])->assertRedirect();
        $this->post(route('admin.viewings.decide',$viewing),[
            'decision'=>'confirmed',
        ])->assertStatus(409);
        $this->assertSame('legacy_review',$viewing->fresh()->status);
        $this->assertDatabaseCount('account_notifications',0);
        $this->assertDatabaseHas('viewing_events',[
            'viewing_request_id'=>$viewing->id,'event_type'=>'admin_review_release',
        ]);
    }

    public function test_viewing_transition_evidence_cannot_be_deleted_by_rollback(): void
    {
        $listing=$this->published('viewing-rollback');
        $customer=User::factory()->create();
        $viewing=$this->requestViewing($customer,$listing);
        $this->assertDatabaseCount('viewing_events',1);
        $migration=require database_path(
            'migrations/2026_11_11_000003_add_viewing_decision_history_and_review.php');
        try {
            $migration->down();
            $this->fail('Rollback must preserve all viewing event evidence.');
        } catch (\RuntimeException $error) {
            $this->assertStringContainsString(
                'Cannot discard immutable viewing decision history',
                $error->getMessage()
            );
        }
        $this->assertTrue(Schema::hasTable('viewing_events'));
        $this->assertDatabaseCount('viewing_events',1);
    }

    public function test_customer_archive_migration_refuses_data_destructive_rollback(): void
    {
        $listing=$this->published('rollback-archive');
        $batch=DB::table('legacy_import_batches')->insertGetId([
            'batch_uuid'=>'eeeeeeee-eeee-4eee-8eee-eeeeeeeeeeee',
            'source_schema_label'=>'isolated_legacy_clone',
            'status'=>'staged_needs_review',
            'mapped_records'=>1,
            'started_at'=>now(),'created_at'=>now(),'updated_at'=>now(),
        ]);
        DB::table('legacy_customer_inquiries')->insert([
            'batch_id'=>$batch,
            'source_uuid'=>'33333333-3333-4333-8333-333333333333',
            'company_id'=>$listing->property->company_id,
            'contact_name'=>'Archive owner','source'=>'website',
            'source_status'=>'new','message'=>'Synthetic',
            'created_at'=>now(),'updated_at'=>now(),
        ]);
        $migration=require database_path(
            'migrations/2026_11_11_000004_create_archived_customer_review_records.php');
        try {
            $migration->down();
            $this->fail('Rollback must not destroy customer archive records.');
        } catch (\RuntimeException $error) {
            $this->assertStringContainsString('Cannot discard archived customer',
                $error->getMessage());
        }
        $this->assertTrue(Schema::hasTable('legacy_customer_inquiries'));
        $this->assertDatabaseCount('legacy_customer_inquiries',1);
    }
}
