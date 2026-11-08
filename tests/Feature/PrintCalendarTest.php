<?php

namespace Tests\Feature;

use App\Models\FamilyEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\CreatesFamily;
use Tests\TestCase;

class PrintCalendarTest extends TestCase
{
    use CreatesFamily, RefreshDatabase;

    public function test_orientation_defaults_to_landscape_and_is_preserved_when_navigating(): void
    {
        $family = $this->familyFor();
        $this->actingAs($family->owner);

        foreach (['', '&orientation=invalid', '&orientation[]=portrait'] as $query) {
            $this->get('/print?month=2026-11'.$query)->assertOk()
                ->assertViewHas('orientation', 'landscape')->assertSee('size: A4 landscape;', false);
        }

        $this->get('/print?month=2026-11&orientation=portrait')->assertOk()
            ->assertViewHas('orientation', 'portrait')->assertSee('size: A4 portrait;', false)
            ->assertSee(route('print', ['month' => '2026-09', 'orientation' => 'portrait']))
            ->assertSee(route('print', ['month' => '2027-01', 'orientation' => 'portrait']));
    }

    public function test_default_print_contains_two_complete_months_including_past_days(): void
    {
        $this->travelTo(Carbon::parse('2026-11-08 12:00:00'));
        $family = $this->familyFor();
        $event = FamilyEvent::factory()->create(['family_id' => $family->id, 'starts_at' => '2026-11-01 10:00:00']);

        $response = $this->actingAs($family->owner)->get('/print')->assertOk()->assertSee($event->title);
        $months = $response->viewData('months');

        $this->assertSame(['2026-11', '2026-12'], $months->map(fn ($month) => $month['date']->format('Y-m'))->all());
        $this->assertCount(6, $months[0]['weeks']);
        $this->assertCount(30, $months[0]['weeks']->flatten(1)->where('is_current_month', true));
        $this->assertSame('2026-10-26', $months[0]['weeks']->first()->first()['date']->toDateString());
        $this->assertSame('2026-12-06', $months[0]['weeks']->last()->last()['date']->toDateString());
    }

    public function test_start_month_handles_leap_years_and_year_changes_without_day_overflow(): void
    {
        $this->travelTo(Carbon::parse('2026-01-31 12:00:00'));
        $family = $this->familyFor();
        $this->actingAs($family->owner);

        $months = $this->get('/print?month=2028-02')->assertOk()->viewData('months');
        $this->assertSame('2028-02-01', $months[0]['date']->toDateString());
        $this->assertCount(29, $months[0]['weeks']->flatten(1)->where('is_current_month', true));

        $response = $this->get('/print?month=2026-12')->assertOk();
        $this->assertSame('2027-01', $response->viewData('months')[1]['date']->format('Y-m'));
        $response->assertViewHas('previousMonth', '2026-10')->assertViewHas('nextMonth', '2027-02');
    }

    public function test_multiday_events_are_assigned_to_each_date_with_exclusive_all_day_end(): void
    {
        $family = $this->familyFor();
        $holiday = FamilyEvent::factory()->create([
            'family_id' => $family->id, 'all_day' => true,
            'starts_at' => '2026-10-30 00:00:00', 'ends_at' => '2026-11-03 00:00:00',
        ]);
        $trip = FamilyEvent::factory()->create([
            'family_id' => $family->id, 'all_day' => false,
            'starts_at' => '2026-11-30 18:00:00', 'ends_at' => '2026-12-02 10:00:00',
        ]);
        $inclusiveEnd = FamilyEvent::factory()->create([
            'family_id' => $family->id, 'all_day' => true,
            'starts_at' => '2026-12-05 00:00:00', 'ends_at' => '2026-12-06 23:59:00',
        ]);
        $response = $this->actingAs($family->owner)->get('/print?month=2026-11')->assertOk();
        $days = $response->viewData('months')->flatMap(fn ($month) => $month['weeks']->flatten(1))
            ->where('is_current_month', true)->keyBy(fn ($day) => $day['date']->toDateString());

        foreach (['2026-11-01', '2026-11-02'] as $date) {
            $this->assertTrue($days[$date]['events']->contains('id', $holiday->id));
        }
        $this->assertFalse($days['2026-11-03']['events']->contains('id', $holiday->id));
        foreach (['2026-11-30', '2026-12-01', '2026-12-02'] as $date) {
            $this->assertTrue($days[$date]['events']->contains('id', $trip->id));
        }
        $this->assertTrue($days['2026-12-06']['events']->contains('id', $inclusiveEnd->id));
        $this->assertSame('2026-11-01', $response->viewData('month')->toDateString());
        $this->assertTrue($response->viewData('months')->flatMap(fn ($month) => $month['weeks']->flatten(1))
            ->where('is_current_month', false)->every(fn ($day) => $day['events']->isEmpty()));
    }

    public function test_print_excludes_other_families_and_events_outside_the_selected_months(): void
    {
        $family = $this->familyFor();
        $foreign = FamilyEvent::factory()->create(['starts_at' => '2026-11-10 12:00:00', 'title' => 'Fremder Termin']);
        FamilyEvent::factory()->create(['family_id' => $family->id, 'starts_at' => '2026-10-31 10:00:00', 'title' => 'Vor dem Zeitraum']);
        FamilyEvent::factory()->create(['family_id' => $family->id, 'starts_at' => '2027-01-01 00:00:00', 'title' => 'Nach dem Zeitraum']);
        FamilyEvent::factory()->create([
            'family_id' => $family->id, 'starts_at' => '2026-10-30 00:00:00', 'ends_at' => '2026-11-01 00:00:00',
            'all_day' => true, 'title' => 'Schon abgeschlossene Ferien',
        ]);

        $this->actingAs($family->owner)->get('/print?month=2026-11')->assertOk()
            ->assertDontSee($foreign->title)->assertDontSee('Vor dem Zeitraum')->assertDontSee('Nach dem Zeitraum')
            ->assertDontSee('Schon abgeschlossene Ferien');
    }

    public function test_print_does_not_truncate_events_and_escapes_titles(): void
    {
        $family = $this->familyFor();
        foreach (range(1, 40) as $index) {
            FamilyEvent::factory()->create([
                'family_id' => $family->id, 'starts_at' => Carbon::parse('2026-11-01 10:00:00')->addDays($index - 1),
                'title' => 'Drucktermin '.$index,
            ]);
        }
        FamilyEvent::factory()->create([
            'family_id' => $family->id, 'starts_at' => '2026-12-20 10:00:00', 'title' => '<script>alert(1)</script>',
        ]);

        $response = $this->actingAs($family->owner)->get('/print?month=2026-11')->assertOk();
        $response->assertSee('Drucktermin 40')->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
        $this->assertSame(41, $response->viewData('months')->flatMap(fn ($month) => $month['weeks']->flatten(1))
            ->sum(fn ($day) => $day['events']->count()));
    }

    public function test_invalid_months_fall_back_to_current_month_and_empty_calendars_still_render(): void
    {
        $this->travelTo(Carbon::parse('2026-11-08 12:00:00'));
        $family = $this->familyFor();
        $this->actingAs($family->owner);

        foreach (['month=2026-13', 'month=2026-02-01', 'month=invalid', 'month[]=2026-11', 'month=0000-01'] as $query) {
            $response = $this->get('/print?'.$query)->assertOk();
            $this->assertSame('2026-11', $response->viewData('month')->format('Y-m'));
            $this->assertCount(2, $response->viewData('months'));
        }
    }

    public function test_account_without_family_gets_a_helpful_empty_state(): void
    {
        $this->actingAs(User::factory()->create())->get('/print')->assertOk()
            ->assertSee('Noch keine Familie verbunden')->assertDontSee('data-print', false);
    }
}
