<?php

use App\Models\Customer;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function supplierPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Northwind Textiles',
        'phone' => '020 7946 0102',
        'email' => 'orders@northwind-textiles.example',
        'address' => 'Unit 4, Mill Road, Leeds',
        'notes' => 'Ships Tuesdays.',
        'is_active' => true,
    ], $overrides);
}

it('shows the supplier list', function () {
    Supplier::factory()->create(['name' => 'Contoso Fabrics']);

    $this->get('/suppliers')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('suppliers/index')
            ->has('rows.data', 1)
            ->where('rows.data.0.name', 'Contoso Fabrics')
        );
});

it('creates a supplier', function () {
    $this->post('/suppliers', supplierPayload())
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $supplier = Supplier::query()->firstOrFail();

    expect($supplier->name)->toBe('Northwind Textiles')
        ->and($supplier->email)->toBe('orders@northwind-textiles.example')
        ->and($supplier->is_active)->toBeTrue();
});

it('creates a supplier from a name alone', function () {
    $this->post('/suppliers', ['name' => 'A Man With A Van'])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $supplier = Supplier::query()->firstOrFail();

    expect($supplier->name)->toBe('A Man With A Van')
        ->and($supplier->phone)->toBeNull()
        ->and($supplier->email)->toBeNull()
        ->and($supplier->is_active)->toBeTrue();
});

it('requires a name', function () {
    $this->post('/suppliers', supplierPayload(['name' => '']))
        ->assertSessionHasErrors('name');

    expect(Supplier::query()->count())->toBe(0);
});

it('rejects a duplicate name', function () {
    Supplier::factory()->create(['name' => 'Northwind Textiles']);

    $this->post('/suppliers', supplierPayload())->assertSessionHasErrors('name');

    expect(Supplier::query()->count())->toBe(1);
});

it('lets a supplier keep its own name when updated', function () {
    $supplier = Supplier::factory()->create(['name' => 'Northwind Textiles']);

    $this->put("/suppliers/{$supplier->id}", supplierPayload(['notes' => 'Now on 30-day terms.']))
        ->assertSessionHasNoErrors();

    expect($supplier->fresh()->notes)->toBe('Now on 30-day terms.');
});

it('rejects a malformed email', function () {
    $this->post('/suppliers', supplierPayload(['email' => 'not-an-email']))
        ->assertSessionHasErrors('email');
});

it('stores blank optional fields as null rather than empty strings', function () {
    $this->post('/suppliers', supplierPayload([
        'phone' => '',
        'email' => '',
        'address' => '',
        'notes' => '',
    ]))->assertSessionHasNoErrors();

    $row = DB::table('suppliers')->first();

    expect($row->phone)->toBeNull()
        ->and($row->email)->toBeNull()
        ->and($row->address)->toBeNull()
        ->and($row->notes)->toBeNull();
});

it('shows the edit screen', function () {
    $supplier = Supplier::factory()->create(['name' => 'Contoso Fabrics']);

    $this->get("/suppliers/{$supplier->id}/edit")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('suppliers/edit')
            ->where('supplier.name', 'Contoso Fabrics')
            ->has('supplier.phone')
        );
});

it('updates a supplier', function () {
    $supplier = Supplier::factory()->create();

    $this->put("/suppliers/{$supplier->id}", supplierPayload([
        'name' => 'Renamed Supplier',
    ]))->assertRedirect()->assertSessionHasNoErrors();

    expect($supplier->fresh()->name)->toBe('Renamed Supplier');
});

it('archives a supplier without deleting it', function () {
    $supplier = Supplier::factory()->create();

    $this->put("/suppliers/{$supplier->id}", supplierPayload([
        'name' => $supplier->name,
        'is_active' => false,
    ]))->assertSessionHasNoErrors();

    expect($supplier->fresh()->is_active)->toBeFalse()
        ->and(Supplier::query()->count())->toBe(1);
});

it('searches by name, phone and email', function () {
    Supplier::factory()->create([
        'name' => 'Northwind Textiles',
        'phone' => '020 7946 0102',
        'email' => 'orders@northwind.example',
    ]);
    Supplier::factory()->create([
        'name' => 'Fabrikam Hardware',
        'phone' => '020 7946 0577',
        'email' => 'sales@fabrikam.example',
    ]);

    $this->get('/suppliers?search=northwind')
        ->assertInertia(fn ($page) => $page->has('rows.data', 1)
            ->where('rows.data.0.name', 'Northwind Textiles'));

    $this->get('/suppliers?search=0577')
        ->assertInertia(fn ($page) => $page->has('rows.data', 1)
            ->where('rows.data.0.name', 'Fabrikam Hardware'));

    $this->get('/suppliers?search=fabrikam.example')
        ->assertInertia(fn ($page) => $page->has('rows.data', 1)
            ->where('rows.data.0.name', 'Fabrikam Hardware'));
});

it('filters the list by status', function () {
    Supplier::factory()->create(['name' => 'Current']);
    Supplier::factory()->inactive()->create(['name' => 'Archived']);

    $this->get('/suppliers?status=inactive')
        ->assertInertia(fn ($page) => $page->has('rows.data', 1)
            ->where('rows.data.0.name', 'Archived'));

    $this->get('/suppliers?status=active')
        ->assertInertia(fn ($page) => $page->has('rows.data', 1)
            ->where('rows.data.0.name', 'Current'));
});

it('deletes a supplier', function () {
    $supplier = Supplier::factory()->create();

    $this->delete("/suppliers/{$supplier->id}")->assertRedirect('/suppliers');

    expect(Supplier::query()->count())->toBe(0);
});

it('keeps guests out', function () {
    auth()->logout();

    $this->get('/suppliers')->assertRedirect('/login');
    $this->post('/suppliers', supplierPayload())->assertRedirect('/login');
});

/**
 * Customers used to be asserted absent here. They exist now — a sale belongs to
 * one, and a customer can carry a loan — so this asserts the two counterparty
 * sections are separate rather than that one of them is missing.
 */
it('keeps customers as their own section, apart from suppliers', function () {
    $this->get('/customers')->assertOk();

    // A supplier is not a customer: neither list shows the other's rows.
    Supplier::factory()->create(['name' => 'Northwind Textiles']);
    Customer::factory()->create(['name' => 'Ahmed Karim']);

    $this->get('/suppliers')->assertInertia(fn ($page) => $page
        ->has('rows.data', 1)
        ->where('rows.data.0.name', 'Northwind Textiles'));

    $this->get('/customers')->assertInertia(fn ($page) => $page
        ->has('rows.data', 1)
        ->where('rows.data.0.name', 'Ahmed Karim'));
});

it('gives suppliers no statement screen, unlike customers', function () {
    $supplier = Supplier::factory()->create();
    $customer = Customer::factory()->create();

    // A supplier has an edit screen and nothing else; `show` is not routed, so
    // the bare id is a method mismatch rather than a page.
    $this->get("/suppliers/{$supplier->id}")->assertStatus(405);

    // A customer's is their account: what they bought, paid, and still owe.
    $this->get("/customers/{$customer->id}")->assertOk();
});
