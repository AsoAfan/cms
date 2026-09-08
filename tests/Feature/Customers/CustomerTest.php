<?php

use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\Sale;
use App\Models\User;
use Database\Seeders\CustomerSeeder;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function customerPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Ahmed Karim',
        'phone' => '0770 145 8823',
        'email' => 'ahmed.karim@example.com',
        'address' => 'Bakhtiari, Sulaymaniyah',
        'notes' => 'Buys for the whole street.',
        'is_active' => true,
    ], $overrides);
}

it('shows the customer list', function () {
    Customer::factory()->create(['name' => 'Layla Hassan']);

    $this->get('/customers')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('customers/index')
            ->has('rows.data', 1)
            ->where('rows.data.0.name', 'Layla Hassan')
            ->where('rows.data.0.balance', 0)
            ->where('owedTotal', 0)
        );
});

it('creates a customer', function () {
    $this->post('/customers', customerPayload())
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $customer = Customer::query()->firstOrFail();

    expect($customer->name)->toBe('Ahmed Karim')
        ->and($customer->email)->toBe('ahmed.karim@example.com')
        ->and($customer->is_active)->toBeTrue();
});

it('creates a customer from a name alone', function () {
    $this->post('/customers', ['name' => 'The man from the market'])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $customer = Customer::query()->firstOrFail();

    expect($customer->name)->toBe('The man from the market')
        ->and($customer->phone)->toBeNull()
        ->and($customer->is_active)->toBeTrue();
});

it('requires a name', function () {
    $this->post('/customers', customerPayload(['name' => '']))
        ->assertSessionHasErrors('name');

    expect(Customer::query()->count())->toBe(0);
});

it('rejects a duplicate name so a customer stays pickable', function () {
    Customer::factory()->create(['name' => 'Ahmed Karim']);

    $this->post('/customers', customerPayload())->assertSessionHasErrors('name');

    expect(Customer::query()->count())->toBe(1);
});

it('lets a customer keep their own name when updated', function () {
    $customer = Customer::factory()->create(['name' => 'Ahmed Karim']);

    $this->put("/customers/{$customer->id}", customerPayload(['notes' => 'Pays at the end of the month.']))
        ->assertSessionHasNoErrors();

    expect($customer->fresh()->notes)->toBe('Pays at the end of the month.');
});

it('rejects a malformed email', function () {
    $this->post('/customers', customerPayload(['email' => 'not-an-email']))
        ->assertSessionHasErrors('email');
});

it('stores blank optional fields as null rather than empty strings', function () {
    $this->post('/customers', customerPayload([
        'phone' => '',
        'email' => '',
        'address' => '',
        'notes' => '',
    ]))->assertSessionHasNoErrors();

    $row = DB::table('customers')->first();

    expect($row->phone)->toBeNull()
        ->and($row->email)->toBeNull()
        ->and($row->address)->toBeNull()
        ->and($row->notes)->toBeNull();
});

it('shows the edit screen', function () {
    $customer = Customer::factory()->create(['name' => 'Layla Hassan']);

    $this->get("/customers/{$customer->id}/edit")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('customers/edit')
            ->where('customer.name', 'Layla Hassan')
            ->has('customer.phone')
        );
});

it('archives a customer without deleting them', function () {
    $customer = Customer::factory()->create();

    $this->put("/customers/{$customer->id}", customerPayload([
        'name' => $customer->name,
        'is_active' => false,
    ]))->assertSessionHasNoErrors();

    expect($customer->fresh()->is_active)->toBeFalse()
        ->and(Customer::query()->count())->toBe(1);
});

it('searches by name, phone and email', function () {
    Customer::factory()->create([
        'name' => 'Ahmed Karim',
        'phone' => '0770 145 8823',
        'email' => 'ahmed@example.com',
    ]);
    Customer::factory()->create([
        'name' => 'Rebaz Interiors',
        'phone' => '0773 660 1290',
        'email' => 'orders@rebaz.example',
    ]);

    $this->get('/customers?search=ahmed')
        ->assertInertia(fn ($page) => $page->has('rows.data', 1)
            ->where('rows.data.0.name', 'Ahmed Karim'));

    $this->get('/customers?search=1290')
        ->assertInertia(fn ($page) => $page->has('rows.data', 1)
            ->where('rows.data.0.name', 'Rebaz Interiors'));

    $this->get('/customers?search=rebaz.example')
        ->assertInertia(fn ($page) => $page->has('rows.data', 1)
            ->where('rows.data.0.name', 'Rebaz Interiors'));
});

it('filters the list by status', function () {
    Customer::factory()->create(['name' => 'Current']);
    Customer::factory()->inactive()->create(['name' => 'Gone quiet']);

    $this->get('/customers?status=inactive')
        ->assertInertia(fn ($page) => $page->has('rows.data', 1)
            ->where('rows.data.0.name', 'Gone quiet'));

    $this->get('/customers?status=active')
        ->assertInertia(fn ($page) => $page->has('rows.data', 1)
            ->where('rows.data.0.name', 'Current'));
});

it('deletes a customer with no history', function () {
    $customer = Customer::factory()->create();

    $this->delete("/customers/{$customer->id}")->assertRedirect('/customers');

    expect(Customer::query()->count())->toBe(0);
});

it('refuses to delete a customer with sales rather than letting the key error', function () {
    $customer = Customer::factory()->create(['name' => 'Ahmed Karim']);
    Sale::factory()->forCustomer($customer)->create();

    $this->delete("/customers/{$customer->id}")->assertRedirect();

    expect(Customer::query()->count())->toBe(1)
        ->and(Sale::query()->count())->toBe(1);
});

it('refuses to delete a customer with payments', function () {
    $customer = Customer::factory()->create();
    CustomerPayment::factory()->forCustomer($customer)->create();

    $this->delete("/customers/{$customer->id}")->assertRedirect();

    expect(Customer::query()->count())->toBe(1);
});

it('keeps guests out', function () {
    auth()->logout();

    $this->get('/customers')->assertRedirect('/login');
    $this->post('/customers', customerPayload())->assertRedirect('/login');
});

it('seeds a walk-in customer for counter trade', function () {
    $this->seed(CustomerSeeder::class);

    expect(Customer::query()->where('name', Customer::WALK_IN)->exists())->toBeTrue();
});
