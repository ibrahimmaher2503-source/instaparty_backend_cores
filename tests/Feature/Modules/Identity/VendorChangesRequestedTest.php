<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Shared\Domain\Enums\ChangeRequestStatus;
use App\Modules\Shared\Domain\Models\ChangeRequest;
use App\Modules\Shared\Domain\Models\ChangeRequestItem;

describe('Vendor changes-requested workflow', function () {
    let($admin, null);
    let($vendor, null);
    let($vendorProfile, null);

    beforeEach(function () {
        $this->admin = User::factory()->admin()->create();
        $this->vendor = User::factory()->vendor()->create();
        $this->vendorProfile = VendorProfile::factory()
            ->for($this->vendor, 'user')
            ->pending()
            ->create();
    });

    describe('Request vendor changes', function () {
        it('admin can request changes and creates change request', function () {
            $payload = [
                'items' => [
                    [
                        'field_path' => 'documents.cr_document',
                        'requested_change_en' => 'CR document is blurry — please re-upload a clear scan',
                        'requested_change_ar' => 'مستند السجل التجاري غير واضح — يرجى رفع نسخة واضحة',
                    ],
                    [
                        'field_path' => 'bank_account.iban',
                        'requested_change_en' => 'IBAN format is invalid — please verify and correct',
                        'requested_change_ar' => 'صيغة IBAN غير صحيحة — يرجى التحقق والتصحيح',
                    ],
                ],
            ];

            $response = $this
                ->actingAs($this->admin, 'sanctum')
                ->postJson("/api/v1/admin/vendor-profiles/{$this->vendorProfile->public_id}/change-requests", $payload, [
                    'Idempotency-Key' => 'test-key-1',
                ])
                ->assertStatus(201);

            expect($response->json('data'))->toHaveKeys(['public_id', 'subject_type', 'subject_id_public', 'status', 'cycle_number', 'items', 'created_at']);
            expect($response->json('data.subject_type'))->toBe('vendor_profile');
            expect($response->json('data.subject_id_public'))->toBe($this->vendorProfile->public_id);
            expect($response->json('data.status'))->toBe('open');
            expect($response->json('data.cycle_number'))->toBe(1);
            expect($response->json('data.items'))->toHaveCount(2);

            $changeRequest = ChangeRequest::where('subject_id', $this->vendorProfile->id)->first();
            expect($changeRequest)->not->toBeNull();
            expect($changeRequest->status)->toBe(ChangeRequestStatus::Open);
            expect($changeRequest->cycle_number)->toBe(1);

            $items = ChangeRequestItem::where('change_request_id', $changeRequest->id)->get();
            expect($items)->toHaveCount(2);
            expect($items[0]->item_status)->toBe('pending');
            expect($items[1]->item_status)->toBe('pending');
        })->group('vendor-changes');

        it('vendor profile status becomes changes_requested after admin request', function () {
            $this->actingAs($this->admin, 'sanctum')
                ->postJson("/api/v1/admin/vendor-profiles/{$this->vendorProfile->public_id}/change-requests", [
                    'items' => [
                        [
                            'field_path' => 'documents.cr_document',
                            'requested_change_en' => 'Please reupload',
                            'requested_change_ar' => 'يرجى إعادة التحميل',
                        ],
                    ],
                ], [
                    'Idempotency-Key' => 'test-key-2',
                ]);

            $this->vendorProfile->refresh();
            expect($this->vendorProfile->approval_status->value)->toBe('changes_requested');
        })->group('vendor-changes');

        it('returns 201 with correct response shape matching contract', function () {
            $response = $this
                ->actingAs($this->admin, 'sanctum')
                ->postJson("/api/v1/admin/vendor-profiles/{$this->vendorProfile->public_id}/change-requests", [
                    'items' => [
                        [
                            'field_path' => 'test_field',
                            'requested_change_en' => 'Test change in English',
                            'requested_change_ar' => 'تغيير اختبار بالعربية',
                        ],
                    ],
                ], [
                    'Idempotency-Key' => 'test-key-3',
                ]);

            expect($response->status())->toBe(201);
            expect($response->json('data.items.0'))->toHaveKeys(['public_id', 'field_path', 'requested_change_en', 'requested_change_ar', 'item_status']);
        })->group('vendor-changes');

        it('persists bilingual fields exactly as provided', function () {
            $enText = 'English change request text';
            $arText = 'نص طلب التغيير العربي';

            $this->actingAs($this->admin, 'sanctum')
                ->postJson("/api/v1/admin/vendor-profiles/{$this->vendorProfile->public_id}/change-requests", [
                    'items' => [
                        [
                            'field_path' => 'test_field',
                            'requested_change_en' => $enText,
                            'requested_change_ar' => $arText,
                        ],
                    ],
                ], [
                    'Idempotency-Key' => 'test-key-4',
                ]);

            $item = ChangeRequestItem::where('change_request_id', '>', 0)->latest()->first();
            expect($item->requested_change_en)->toBe($enText);
            expect($item->requested_change_ar)->toBe($arText);
        })->group('vendor-changes');

        it('returns 401 when not authenticated', function () {
            $this->postJson("/api/v1/admin/vendor-profiles/{$this->vendorProfile->public_id}/change-requests", [
                'items' => [
                    [
                        'field_path' => 'test',
                        'requested_change_en' => 'test',
                        'requested_change_ar' => 'اختبار',
                    ],
                ],
            ], [
                'Idempotency-Key' => 'test-key-5',
            ])->assertStatus(401);
        })->group('vendor-changes');

        it('returns 403 for non-admin user', function () {
            $this->actingAs($this->vendor, 'sanctum')
                ->postJson("/api/v1/admin/vendor-profiles/{$this->vendorProfile->public_id}/change-requests", [
                    'items' => [
                        [
                            'field_path' => 'test',
                            'requested_change_en' => 'test',
                            'requested_change_ar' => 'اختبار',
                        ],
                    ],
                ], [
                    'Idempotency-Key' => 'test-key-6',
                ])->assertStatus(403);
        })->group('vendor-changes');

        it('returns 422 when requested_change_ar is empty string', function () {
            $this->actingAs($this->admin, 'sanctum')
                ->postJson("/api/v1/admin/vendor-profiles/{$this->vendorProfile->public_id}/change-requests", [
                    'items' => [
                        [
                            'field_path' => 'test_field',
                            'requested_change_en' => 'Valid English text',
                            'requested_change_ar' => '',
                        ],
                    ],
                ], [
                    'Idempotency-Key' => 'test-key-7',
                ])->assertStatus(422);
        })->group('vendor-changes');

        it('returns 409 when idempotency key is duplicated', function () {
            $payload = [
                'items' => [
                    [
                        'field_path' => 'test',
                        'requested_change_en' => 'test',
                        'requested_change_ar' => 'اختبار',
                    ],
                ],
            ];
            $idempotencyKey = 'test-key-8';

            $this->actingAs($this->admin, 'sanctum')
                ->postJson("/api/v1/admin/vendor-profiles/{$this->vendorProfile->public_id}/change-requests", $payload, [
                    'Idempotency-Key' => $idempotencyKey,
                ])
                ->assertStatus(201);

            $this->actingAs($this->admin, 'sanctum')
                ->postJson("/api/v1/admin/vendor-profiles/{$this->vendorProfile->public_id}/change-requests", $payload, [
                    'Idempotency-Key' => $idempotencyKey,
                ])
                ->assertStatus(409);
        })->group('vendor-changes');

        it('returns 409 when vendor profile already has open change request', function () {
            $this->actingAs($this->admin, 'sanctum')
                ->postJson("/api/v1/admin/vendor-profiles/{$this->vendorProfile->public_id}/change-requests", [
                    'items' => [
                        [
                            'field_path' => 'test1',
                            'requested_change_en' => 'First request',
                            'requested_change_ar' => 'الطلب الأول',
                        ],
                    ],
                ], [
                    'Idempotency-Key' => 'test-key-9a',
                ]);

            $this->actingAs($this->admin, 'sanctum')
                ->postJson("/api/v1/admin/vendor-profiles/{$this->vendorProfile->public_id}/change-requests", [
                    'items' => [
                        [
                            'field_path' => 'test2',
                            'requested_change_en' => 'Second request',
                            'requested_change_ar' => 'الطلب الثاني',
                        ],
                    ],
                ], [
                    'Idempotency-Key' => 'test-key-9b',
                ])
                ->assertStatus(409);
        })->group('vendor-changes');
    });

    describe('Vendor resubmit after changes', function () {
        beforeEach(function () {
            $this->changeRequest = ChangeRequest::create([
                'public_id' => fake()->ulid(),
                'subject_type' => 'vendor_profile',
                'subject_id' => $this->vendorProfile->id,
                'requested_by_admin_id' => $this->admin->id,
                'status' => ChangeRequestStatus::Open,
                'cycle_number' => 1,
            ]);

            $this->item1 = ChangeRequestItem::create([
                'public_id' => fake()->ulid(),
                'change_request_id' => $this->changeRequest->id,
                'field_path' => 'documents.cr_document',
                'requested_change_en' => 'Please reupload',
                'requested_change_ar' => 'يرجى إعادة التحميل',
                'item_status' => 'pending',
            ]);

            $this->item2 = ChangeRequestItem::create([
                'public_id' => fake()->ulid(),
                'change_request_id' => $this->changeRequest->id,
                'field_path' => 'bank_account.iban',
                'requested_change_en' => 'Fix IBAN',
                'requested_change_ar' => 'إصلاح IBAN',
                'item_status' => 'pending',
            ]);

            $this->vendorProfile->update(['approval_status' => 'changes_requested']);
        });

        it('vendor can resubmit changes and updates item status to addressed', function () {
            $response = $this
                ->actingAs($this->vendor, 'sanctum')
                ->postJson("/api/v1/vendor/vendor-profiles/{$this->vendorProfile->public_id}/resubmit", [
                    'addressed_item_ids' => [$this->item1->public_id],
                    'waived_item_ids' => [$this->item2->public_id],
                ], [
                    'Idempotency-Key' => 'resubmit-key-1',
                ])
                ->assertStatus(200);

            $this->item1->refresh();
            $this->item2->refresh();
            expect($this->item1->item_status)->toBe('addressed');
            expect($this->item2->item_status)->toBe('waived');
        })->group('vendor-changes');

        it('vendor profile status returns to pending after resubmit', function () {
            $this->actingAs($this->vendor, 'sanctum')
                ->postJson("/api/v1/vendor/vendor-profiles/{$this->vendorProfile->public_id}/resubmit", [
                    'addressed_item_ids' => [$this->item1->public_id, $this->item2->public_id],
                    'waived_item_ids' => [],
                ], [
                    'Idempotency-Key' => 'resubmit-key-2',
                ]);

            $this->vendorProfile->refresh();
            expect($this->vendorProfile->approval_status->value)->toBe('pending');
        })->group('vendor-changes');

        it('change request status becomes resubmitted after vendor resubmit', function () {
            $this->actingAs($this->vendor, 'sanctum')
                ->postJson("/api/v1/vendor/vendor-profiles/{$this->vendorProfile->public_id}/resubmit", [
                    'addressed_item_ids' => [$this->item1->public_id],
                    'waived_item_ids' => [$this->item2->public_id],
                ], [
                    'Idempotency-Key' => 'resubmit-key-3',
                ]);

            $this->changeRequest->refresh();
            expect($this->changeRequest->status)->toBe(ChangeRequestStatus::Resubmitted);
        })->group('vendor-changes');

        it('returns 401 when vendor is not authenticated', function () {
            $this->postJson("/api/v1/vendor/vendor-profiles/{$this->vendorProfile->public_id}/resubmit", [
                'addressed_item_ids' => [$this->item1->public_id],
                'waived_item_ids' => [],
            ], [
                'Idempotency-Key' => 'resubmit-key-4',
            ])->assertStatus(401);
        })->group('vendor-changes');

        it('returns 403 when vendor resubmits another vendor profile', function () {
            $otherVendor = User::factory()->vendor()->create();
            $otherProfile = VendorProfile::factory()
                ->for($otherVendor, 'user')
                ->changesRequested()
                ->create();

            $this->actingAs($this->vendor, 'sanctum')
                ->postJson("/api/v1/vendor/vendor-profiles/{$otherProfile->public_id}/resubmit", [
                    'addressed_item_ids' => [],
                    'waived_item_ids' => [],
                ], [
                    'Idempotency-Key' => 'resubmit-key-5',
                ])
                ->assertStatus(403);
        })->group('vendor-changes');

        it('returns 409 when idempotency key is duplicated', function () {
            $payload = [
                'addressed_item_ids' => [$this->item1->public_id],
                'waived_item_ids' => [$this->item2->public_id],
            ];
            $idempotencyKey = 'resubmit-key-6';

            $this->actingAs($this->vendor, 'sanctum')
                ->postJson("/api/v1/vendor/vendor-profiles/{$this->vendorProfile->public_id}/resubmit", $payload, [
                    'Idempotency-Key' => $idempotencyKey,
                ])
                ->assertStatus(200);

            $this->actingAs($this->vendor, 'sanctum')
                ->postJson("/api/v1/vendor/vendor-profiles/{$this->vendorProfile->public_id}/resubmit", $payload, [
                    'Idempotency-Key' => $idempotencyKey,
                ])
                ->assertStatus(409);
        })->group('vendor-changes');
    });

    describe('3-cycle limit enforcement', function () {
        it('blocks request after 3 cycles', function () {
            // Create 3 complete cycles
            for ($i = 1; $i <= 3; $i++) {
                $changeRequest = ChangeRequest::create([
                    'public_id' => fake()->ulid(),
                    'subject_type' => 'vendor_profile',
                    'subject_id' => $this->vendorProfile->id,
                    'requested_by_admin_id' => $this->admin->id,
                    'status' => ChangeRequestStatus::Resolved,
                    'cycle_number' => $i,
                    'resolved_by_admin_id' => $this->admin->id,
                    'resolved_at' => now(),
                ]);
            }

            // Fourth attempt should be blocked
            $this->actingAs($this->admin, 'sanctum')
                ->postJson("/api/v1/admin/vendor-profiles/{$this->vendorProfile->public_id}/change-requests", [
                    'items' => [
                        [
                            'field_path' => 'test',
                            'requested_change_en' => 'test',
                            'requested_change_ar' => 'اختبار',
                        ],
                    ],
                ], [
                    'Idempotency-Key' => 'test-key-limit',
                ])
                ->assertStatus(422);
        })->group('vendor-changes');
    });
});
