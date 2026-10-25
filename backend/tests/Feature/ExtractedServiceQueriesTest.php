<?php

namespace Tests\Feature;

use App\Models\CaregiverSchedule;
use App\Models\Incident;
use App\Models\Medication;
use App\Models\MedicationAcquisition;
use App\Models\MedicationAdministration;
use App\Models\OlderAdultMedication;
use App\Models\VacationRequest;
use App\Queries\MedicationStatisticsQuery;
use App\Queries\ScheduleCalendarQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\CreatesCareTestData;
use Tests\TestCase;

class ExtractedServiceQueriesTest extends TestCase
{
    use RefreshDatabase;
    use CreatesCareTestData;

    public function test_acquisitions_include_both_period_boundaries_and_load_medication_names(): void
    {
        $medication = Medication::factory()->create();
        $ids = [];
        foreach (['2026-10-22 23:59:59', '2026-10-23 00:00:00', '2026-10-23 23:59:59', '2026-10-24 00:00:00'] as $date) {
            $ids[] = MedicationAcquisition::create([
                'medication_id' => $medication->id, 'quantity' => 2, 'acquired_at' => $date,
            ])->id;
        }

        $rows = app(MedicationStatisticsQuery::class)->acquisitionsForPeriod(
            Carbon::parse('2026-10-23 00:00:00'), Carbon::parse('2026-10-23 23:59:59'),
        );

        $this->assertSame([$ids[1], $ids[2]], $rows->pluck('id')->all());
        foreach ($rows as $row) {
            $this->assertTrue($row->relationLoaded('medication'));
            $this->assertSame($medication->name, $row->medication->name);
        }
    }

    public function test_inventory_preserves_unassigned_inactive_rows_and_administration_counts_when_paginated(): void
    {
        $professional = $this->createApprovedProfessional();
        $adult = $this->createAssignedOlderAdult($professional);
        $assigned = $this->createMedicationAssignment($adult);
        $unassigned = OlderAdultMedication::factory()->create(['older_adult_id' => null, 'is_active' => false]);
        MedicationAdministration::create([
            'older_adult_id' => $adult->id, 'older_adult_medication_id' => $assigned->id,
            'medication_id' => $assigned->medication_id, 'administration_date' => '2026-10-23',
            'administration_time' => '08:00', 'recorded_by' => $professional->id,
        ]);

        $query = app(MedicationStatisticsQuery::class);
        $page = $query->inventory()->paginate(1, ['*'], 'page', 1);
        $this->assertSame(2, $page->total());
        $this->assertSame(2, $page->lastPage());
        $this->assertCount(1, $page->items());
        $rows = $query->inventory()->get()->keyBy('id');
        $this->assertFalse($rows[$unassigned->id]->is_active);
        $this->assertNull($rows[$unassigned->id]->olderAdult);
        $this->assertSame(0, $rows[$unassigned->id]->administrations_count);
        $this->assertSame(1, $rows[$assigned->id]->administrations_count);
        $this->assertTrue($rows[$assigned->id]->relationLoaded('medication'));
        $this->assertTrue($rows[$assigned->id]->relationLoaded('olderAdult'));
        $this->assertSame($adult->id, $rows[$assigned->id]->olderAdult->id);
    }

    public function test_calendar_limits_adult_groups_to_selected_caregivers_and_orders_schedules(): void
    {
        $professional = $this->createApprovedProfessional();
        $other = $this->createApprovedProfessional();
        $later = $this->createAssignedOlderAdult($professional, ['full_name' => 'Zeta sintética']);
        $first = $this->createAssignedOlderAdult($professional, ['full_name' => 'Alfa sintética']);
        $this->createAssignedOlderAdult($other);
        $lateSchedule = CaregiverSchedule::create([
            'user_id' => $professional->id, 'day_of_week' => 5, 'start_time' => '12:00', 'end_time' => '16:00',
        ]);
        $earlySchedule = CaregiverSchedule::create([
            'user_id' => $other->id, 'day_of_week' => 1, 'start_time' => '08:00', 'end_time' => '12:00',
        ]);

        $query = app(ScheduleCalendarQuery::class);
        $groups = $query->olderAdultsByCaregiver(collect([$professional->id]));
        $this->assertSame([$professional->id], $groups->keys()->all());
        $this->assertSame([$first->id, $later->id], $groups[$professional->id]->pluck('id')->all());
        $this->assertTrue($query->olderAdultsByCaregiver(collect())->isEmpty());
        $schedules = $query->schedules();
        $this->assertSame([$earlySchedule->id, $lateSchedule->id], $schedules->pluck('id')->all());
        $this->assertTrue($schedules->every(fn ($schedule) => $schedule->relationLoaded('user')));
    }

    public function test_only_approved_selected_vacations_affect_shifts_but_events_keep_all_overlapping_statuses(): void
    {
        $professional = $this->createApprovedProfessional();
        $other = $this->createApprovedProfessional();
        $atStart = $this->vacation($professional->id, 'approved', '2026-10-01', '2026-10-10');
        $atEnd = $this->vacation($professional->id, 'approved', '2026-10-20', '2026-10-25');
        $pending = $this->vacation($professional->id, 'pending', '2026-10-12', '2026-10-13');
        $rejected = $this->vacation($professional->id, 'rejected', '2026-10-14', '2026-10-15');
        $otherVacation = $this->vacation($other->id, 'approved', '2026-10-16', '2026-10-17');
        $this->vacation($professional->id, 'approved', '2026-10-01', '2026-10-09');
        $this->vacation($professional->id, 'approved', '2026-10-21', '2026-10-25');

        $start = Carbon::parse('2026-10-10');
        $end = Carbon::parse('2026-10-20');
        $query = app(ScheduleCalendarQuery::class);
        $groups = $query->approvedVacationsByCaregiver(collect([$professional->id]), $start, $end);
        $this->assertSame([$professional->id], $groups->keys()->all());
        $this->assertEqualsCanonicalizing([$atStart->id, $atEnd->id], $groups[$professional->id]->pluck('id')->all());
        $this->assertTrue($query->approvedVacationsByCaregiver(collect(), $start, $end)->isEmpty());
        $events = $query->vacationEvents($start, $end);
        $this->assertSame([$atStart->id, $pending->id, $rejected->id, $otherVacation->id, $atEnd->id], $events->pluck('id')->all());
        $this->assertTrue($events->every(fn ($event) => $event->relationLoaded('user')));
    }

    public function test_incident_events_include_date_boundaries_order_null_times_last_and_preload_relations(): void
    {
        $professional = $this->createApprovedProfessional();
        $adult = $this->createAssignedOlderAdult($professional);
        $ids = [];
        foreach ([['2026-10-22', '08:00'], ['2026-10-23', null], ['2026-10-23', '09:00'], ['2026-10-24', '07:00'], ['2026-10-25', '08:00']] as [$date, $time]) {
            $ids[] = Incident::create([
                'older_adult_id' => $adult->id, 'reported_by' => $professional->id,
                'title' => 'Incidente sintético', 'severity' => 'media', 'status' => 'abierto',
                'incident_date' => $date, 'incident_time' => $time,
            ])->id;
        }

        $rows = app(ScheduleCalendarQuery::class)->incidentEvents(Carbon::parse('2026-10-23'), Carbon::parse('2026-10-24'));

        $this->assertSame([$ids[2], $ids[1], $ids[3]], $rows->pluck('id')->all());
        foreach ($rows as $row) {
            $this->assertTrue($row->relationLoaded('olderAdult'));
            $this->assertTrue($row->relationLoaded('reporter'));
            $this->assertSame($adult->id, $row->olderAdult->id);
            $this->assertSame($professional->id, $row->reporter->id);
        }
    }

    private function vacation(int $userId, string $status, string $start, string $end): VacationRequest
    {
        return VacationRequest::create([
            'user_id' => $userId, 'status' => $status, 'start_date' => $start,
            'end_date' => $end, 'reason' => 'Solicitud sintética',
        ]);
    }
}
