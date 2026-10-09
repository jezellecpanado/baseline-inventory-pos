@php
    $activeAdminTab = match ($adminTab) {
        'qr' => 'qr-settings',
        'voids' => 'sale-voids',
        default => in_array($adminTab, ['products', 'users', 'qr-settings', 'history', 'sale-voids'], true) ? $adminTab : 'products',
    };
@endphp

<div class="mx-auto w-full max-w-[1600px] px-gutter py-space-xl sm:px-gutter-tablet lg:px-gutter-desktop">
    <div class="flex w-full flex-col">
        <div class="flex flex-col justify-between gap-space-md border-b border-[#e5e5e7] pb-space-lg md:flex-row md:items-center">
            <div>
                <div class="mb-1 flex items-center gap-space-sm"><span class="inline-block h-2.5 w-2.5 bg-electric-lime"></span><span class="font-label-sm text-label-sm font-bold uppercase tracking-wider text-neutral-500">System Root // Console</span></div>
                <h1 class="font-headline-lg text-headline-lg font-extrabold uppercase tracking-tight text-on-surface">System &amp; Settings</h1>
            </div>
            <div class="flex items-center gap-space-md">
                <div class="flex items-center gap-space-xs border border-[#e5e5e7] bg-neutral-200/60 px-space-md py-space-xs font-mono-data text-body-sm text-neutral-700"><span class="h-2 w-2 animate-pulse bg-electric-lime"></span><span class="font-semibold tracking-wide">ADMIN ACCESS</span></div>
                <button class="flex cursor-pointer items-center gap-space-xs border border-jet-black bg-electric-lime px-space-lg py-space-sm font-label-lg text-label-lg font-bold uppercase tracking-wider text-jet-black shadow-sm transition-all hover:bg-electric-lime-hover" type="button" data-modal-open="add-product-modal"><span class="material-symbols-outlined text-[18px] font-bold">add</span>Add Product</button>
            </div>
        </div>

        <nav id="admin-tabs" class="no-scrollbar flex items-center gap-space-xs overflow-x-auto border-b border-[#e5e5e7] pt-space-md pb-space-sm" aria-label="Admin sections">
            @foreach (['products'=>'Products','users'=>'Users','qr-settings'=>'QR Settings','history'=>'History','sale-voids'=>'Sale Voids'] as $key => $label)
                <button id="tab-btn-{{ $key }}" class="admin-tab-button whitespace-nowrap px-space-md py-space-sm font-label-md text-label-md uppercase tracking-wider transition-colors {{ $activeAdminTab === $key ? 'border-b-2 border-electric-lime bg-jet-black text-white' : 'text-neutral-500 hover:bg-neutral-200 hover:text-on-surface' }}" type="button" data-tab="{{ $key }}" aria-controls="panel-{{ $key }}" aria-selected="{{ $activeAdminTab === $key ? 'true' : 'false' }}">{{ $label }}</button>
            @endforeach
        </nav>

        <section class="admin-tab-panel flex w-full flex-col pt-space-lg {{ $activeAdminTab === 'products' ? '' : 'hidden' }}" id="panel-products" aria-label="Products">
            <div class="mb-space-lg flex flex-col items-stretch justify-between gap-space-md sm:flex-row sm:items-center">
                <label class="flex w-full max-w-md items-center gap-space-sm border border-[#e5e5e7] bg-white px-space-md py-space-xs focus-within:border-jet-black"><span class="material-symbols-outlined text-[20px] text-neutral-500">search</span><input id="product-search" class="w-full bg-transparent font-body-md text-body-md text-on-surface placeholder:text-neutral-500 focus:outline-none" placeholder="Filter by Design, Barcode, or Category..." type="search" aria-label="Search products by design, barcode, or category"></label>
                <div class="flex flex-wrap items-center gap-space-sm"><span class="font-mono-data text-body-sm uppercase text-neutral-500">Viewing:</span><span id="product-filter-state" class="border border-[#e5e5e7] bg-white px-space-sm py-1 font-label-md text-label-md font-semibold text-on-surface">All Categories</span><span class="ml-space-sm font-mono-data text-body-sm font-semibold uppercase text-neutral-500">Total Products: {{ $products->count() }}</span></div>
            </div>
            <div class="w-full overflow-x-auto border border-[#e5e5e7] bg-white">
                <table id="product-table" class="w-max min-w-[1300px] border-collapse text-left">
                    <thead><tr class="border-b border-[#e5e5e7] bg-[#f8f8f9] font-label-sm text-label-sm uppercase tracking-wider text-neutral-700"><th class="w-16 px-space-md py-space-sm">Photo</th><th class="px-space-md py-space-sm">Product Design</th><th class="px-space-md py-space-sm">Category</th><th class="px-space-md py-space-sm">Variant</th><th class="w-16 px-space-md py-space-sm">Size</th><th class="px-space-md py-space-sm font-mono-data">Barcode</th><th class="px-space-md py-space-sm text-right font-mono-data">Price</th><th class="whitespace-nowrap px-space-md py-space-sm">Eligible for Promo</th><th class="px-space-md py-space-sm text-center">Status</th><th class="px-space-md py-space-sm text-right">Actions</th></tr></thead>
                    <tbody class="divide-y divide-[#e5e5e7] font-body-md text-body-md text-on-surface">
                        @forelse ($products as $product)
                            <tr class="product-row group transition-colors hover:bg-neutral-100 {{ $product->active ? '' : 'bg-neutral-100/60 opacity-60' }}" data-category="{{ $product->category }}" data-search="{{ strtolower($product->design_name.' '.$product->category.' '.$product->variant.' '.$product->size.' '.$product->barcode) }}">
                                <td class="px-space-md py-space-sm"><div class="flex h-12 w-12 items-center justify-center overflow-hidden border border-[#e5e5e7] bg-white">@if ($product->photo_path)<img class="h-full w-full object-cover" src="{{ asset('storage/'.$product->photo_path) }}" alt="{{ $product->design_name }}" loading="lazy">@else<span class="material-symbols-outlined text-secondary">image</span>@endif</div></td>
                                <td class="px-space-md py-space-sm font-headline-sm text-body-md font-semibold text-on-surface">{{ $product->design_name }}</td>
                                <td class="px-space-md py-space-sm"><span class="border border-[#e5e5e7] bg-[#f4f4f5] px-space-xs py-0.5 font-label-sm text-label-sm font-bold uppercase text-neutral-700">{{ $product->category }}</span></td>
                                <td class="px-space-md py-space-sm text-neutral-500">{{ $product->variant }}</td><td class="px-space-md py-space-sm font-mono-data font-semibold">{{ $product->size }}</td><td class="px-space-md py-space-sm font-mono-data text-neutral-500">{{ $product->barcode }}</td><td class="px-space-md py-space-sm text-right font-mono-data font-semibold">₱{{ number_format((float) $product->standard_price, 2) }}</td>
                                <td class="px-space-md py-space-sm"><form class="product-promotion-form" method="POST" action="{{ route('admin.store', ['action' => 'product-promotion']) }}">@csrf<input type="hidden" name="product_id" value="{{ $product->id }}"><input type="hidden" name="eligible" value="0"><label class="flex items-center gap-2 whitespace-nowrap text-xs font-semibold"><input class="product-promo-eligible" type="checkbox" name="eligible" value="1" @checked($product->promotion_id !== null)> Eligible for Promo</label></form></td>
                                <td class="px-space-md py-space-sm text-center"><form method="POST" action="{{ route('admin.store', ['action' => 'product-status']) }}">@csrf<input type="hidden" name="product_id" value="{{ $product->id }}"><input type="hidden" name="active" value="0"><label class="relative inline-flex cursor-pointer items-center" title="{{ $product->active ? 'Deactivate product' : 'Activate product' }}"><input class="peer sr-only" type="checkbox" name="active" value="1" @checked($product->active) onchange="this.form.requestSubmit()" aria-label="Set {{ $product->design_name }} {{ $product->size }} active"><span class="h-5 w-9 border border-neutral-300 bg-[#e5e5e7] transition peer-checked:border-jet-black peer-checked:bg-jet-black after:absolute after:left-[2px] after:top-[2px] after:h-4 after:w-4 after:border after:border-[#e5e5e7] after:bg-white after:transition-all peer-checked:after:translate-x-full peer-checked:after:bg-electric-lime"></span></label></form></td>
                                <td class="px-space-md py-space-sm text-right"><div class="flex items-center justify-end gap-2"><a class="p-1 text-neutral-500 transition-colors hover:text-jet-black" href="{{ route('admin.index', ['tab' => 'products', 'edit' => $product->id]) }}" title="Edit product"><span class="material-symbols-outlined text-[18px]">edit</span></a><form method="POST" action="{{ route('admin.store', ['action' => 'product-delete']) }}" onsubmit="return confirm('Are you sure you want to delete this?')">@csrf<input type="hidden" name="id" value="{{ $product->id }}"><button class="font-label-sm text-label-sm font-semibold uppercase text-error underline hover:text-red-800" type="submit">Delete</button></form></div></td>
                            </tr>
                        @empty
                            <tr><td class="py-16 text-center text-secondary" colspan="10"><div class="flex flex-col items-center gap-2"><span class="material-symbols-outlined text-3xl">add_photo_alternate</span><strong class="text-on-surface">No products added</strong><span class="text-xs">Add a product to begin stocking.</span></div></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <p id="product-no-results" class="hidden border border-dashed border-[#e5e5e7] p-space-lg text-center text-sm text-neutral-500">No products match this filter.</p>
        </section>

        <section class="admin-tab-panel {{ $activeAdminTab === 'users' ? '' : 'hidden' }} w-full flex-col pt-space-lg" id="panel-users" aria-label="Users">
            <div class="mb-space-md flex flex-col justify-between gap-space-sm border-b border-[#e5e5e7] pb-space-md sm:flex-row sm:items-center"><div><h2 class="font-headline-sm text-headline-sm font-bold uppercase text-on-surface">Staff &amp; Access Roles</h2><p class="mt-1 text-sm text-neutral-500">Manage Admin, Warehouse Staff, and POS Staff access.</p></div><button class="border border-jet-black bg-jet-black px-space-md py-space-xs font-label-sm text-label-sm font-bold uppercase text-white transition-colors hover:bg-neutral-700" type="button" data-modal-open="user-modal" data-user-create>+ Invite User</button></div>
            <div class="w-full overflow-x-auto border border-[#e5e5e7] bg-white"><table class="w-full min-w-[700px] border-collapse text-left"><thead><tr class="border-b border-[#e5e5e7] bg-[#f8f8f9] font-label-sm text-label-sm font-bold uppercase text-neutral-700"><th class="px-space-md py-space-sm">Name / Username</th><th class="px-space-md py-space-sm">System Role</th><th class="px-space-md py-space-sm">Status</th><th class="px-space-md py-space-sm text-right">Actions</th></tr></thead><tbody class="divide-y divide-[#e5e5e7] font-body-sm">
                @forelse ($users as $person)
                    <tr class="hover:bg-neutral-100"><td class="px-space-md py-space-sm"><span class="font-semibold">{{ $person->name }}</span><span class="mt-0.5 block text-xs text-neutral-500">{{ $person->username }}</span></td><td class="px-space-md py-space-sm">{{ ['admin' => 'Admin', 'warehouse_staff' => 'Warehouse Staff', 'pos_staff' => 'POS Staff'][$person->role] ?? $person->role }}</td><td class="px-space-md py-space-sm"><span class="border border-[#e5e5e7] px-space-xs py-0.5 text-xs {{ $person->active ? 'bg-electric-lime/20 text-jet-black' : 'bg-[#f4f4f5] text-neutral-500' }}">{{ $person->active ? 'Active' : 'Inactive' }}</span></td><td class="px-space-md py-space-sm"><div class="flex items-center justify-end gap-space-sm"><button class="font-label-sm text-label-sm font-semibold uppercase text-neutral-500 underline hover:text-jet-black" type="button" data-user-edit data-id="{{ $person->id }}" data-name="{{ $person->name }}" data-username="{{ $person->username }}" data-role="{{ $person->role }}" data-active="{{ $person->active ? '1' : '0' }}">Edit / Reset</button><form method="POST" action="{{ route('admin.store', ['action' => 'user-delete']) }}" onsubmit="return confirm('Are you sure you want to delete this?')">@csrf<input type="hidden" name="id" value="{{ $person->id }}"><button class="font-label-sm text-label-sm font-semibold uppercase text-error underline hover:text-red-800" type="submit">Delete</button></form></div></td></tr>
                @empty
                    <tr><td colspan="4" class="px-space-md py-space-lg text-center text-neutral-500">No users found.</td></tr>
                @endforelse
            </tbody></table></div>
        </section>

        <section class="admin-tab-panel {{ $activeAdminTab === 'qr-settings' ? '' : 'hidden' }} w-full flex-col pt-space-lg" id="panel-qr-settings" aria-label="QR Settings">
            <div class="mb-space-lg border-b border-[#e5e5e7] pb-space-md"><h2 class="font-headline-sm text-headline-sm font-bold uppercase text-on-surface">QR Checkout Settings</h2><p class="mt-1 text-sm text-neutral-500">Set the QR image staff display for manual payment confirmation at checkout.</p></div>
            <div class="grid grid-cols-1 gap-space-lg lg:grid-cols-12"><form class="space-y-space-lg border border-[#e5e5e7] bg-white p-space-lg lg:col-span-5" method="POST" enctype="multipart/form-data" action="{{ route('admin.store', ['action' => 'qr']) }}">@csrf<h3 class="border-b border-[#e5e5e7] pb-space-xs font-label-lg text-label-lg font-bold uppercase tracking-wider text-on-surface">Payment QR Image</h3><p class="text-sm text-neutral-500">QR payments require a staff member to confirm receipt. No payment gateway is connected.</p><label class="form-label">Upload QR image<input class="form-input" type="file" name="qr_image" accept="image/jpeg,image/png,image/webp" required><span class="mt-1 block text-xs font-normal text-neutral-500">JPG, PNG, or WebP · up to 4 MB</span></label><button class="w-full border border-jet-black bg-jet-black px-space-xl py-space-sm font-label-md text-label-md font-bold uppercase tracking-wider text-white transition-colors hover:bg-electric-lime hover:text-jet-black">Save QR Configuration</button></form><div class="flex min-h-64 flex-col items-center justify-center border border-[#e5e5e7] bg-white p-space-lg lg:col-span-7"><h3 class="mb-space-md w-full border-b border-[#e5e5e7] pb-space-xs font-label-sm text-label-sm font-bold uppercase tracking-wider text-neutral-700">Current QR Image</h3>@if ($qrImage)<img class="max-h-80 max-w-full object-contain" src="{{ asset('storage/'.$qrImage) }}" alt="Current payment QR code">@else<div class="flex flex-col items-center text-center text-neutral-500"><span class="material-symbols-outlined text-4xl">qr_code_2</span><p class="mt-2 text-sm">No QR image configured.</p></div>@endif</div></div>
        </section>

        <section class="admin-tab-panel {{ $activeAdminTab === 'history' ? '' : 'hidden' }} w-full flex-col pt-space-lg" id="panel-history" aria-label="History">
            <div class="mb-space-md flex flex-col justify-between gap-space-sm border-b border-[#e5e5e7] pb-space-md lg:flex-row lg:items-end"><div><h2 class="font-headline-sm text-headline-sm font-bold uppercase text-on-surface">Audit Trail &amp; Transaction Logs</h2><p class="mt-1 text-sm text-neutral-500">Read-only inventory movements and transaction records.</p></div><div class="flex flex-wrap items-center gap-space-sm"><form id="admin-history-filter" method="GET" action="{{ route('admin.index') }}" class="flex flex-wrap items-center gap-space-xs"><input type="hidden" name="tab" value="history"><input class="form-input min-w-44" name="history_search" value="{{ request('history_search') }}" placeholder="Filter by reference, product, Barcode..." aria-label="Filter history"><input class="form-input w-36" type="date" name="history_from" value="{{ request('history_from') }}" aria-label="From date"><input class="form-input w-36" type="date" name="history_to" value="{{ request('history_to') }}" aria-label="To date"><select class="form-input w-36" name="location" aria-label="Filter by location"><option value="">All locations</option><option value="Warehouse" @selected(request('location') === 'Warehouse')>Warehouse</option><option value="POS" @selected(request('location') === 'POS')>POS</option></select><select class="form-input w-44" name="movement_type" aria-label="Filter by movement"><option value="">All movements</option>@foreach (['Beginning Inventory','Stock Received','Warehouse → POS Transfer','POS → Warehouse Return','POS Sale','Offline/Warehouse Sale','Exchange Return','Exchange Replacement','Withdrawal','POS Withdrawal','Adjustment','Void/Reversal'] as $movement)<option value="{{ $movement }}" @selected(request('movement_type') === $movement)>{{ $movement }}</option>@endforeach</select><button class="border border-jet-black bg-jet-black px-space-md py-space-xs font-label-sm text-label-sm font-bold uppercase text-white">Filter</button></form><button class="flex items-center gap-1 border border-[#e5e5e7] bg-[#f4f4f5] px-space-md py-space-xs font-label-sm text-label-sm font-bold uppercase transition-colors hover:border-jet-black" type="button" onclick="exportTable('history-table','baseline-history.csv')"><span class="material-symbols-outlined text-base">download</span>Export CSV</button></div></div>
            <div class="w-full overflow-x-auto border border-[#e5e5e7] bg-white"><table id="history-table" class="w-full min-w-[850px] border-collapse text-left font-body-sm"><thead><tr class="border-b border-[#e5e5e7] bg-[#f8f8f9] font-label-sm text-label-sm font-bold uppercase text-neutral-700"><th class="px-space-md py-space-sm font-mono-data">Ref Number</th><th class="px-space-md py-space-sm">Event Type</th><th class="px-space-md py-space-sm">Description</th><th class="px-space-md py-space-sm">Actor</th><th class="px-space-md py-space-sm font-mono-data">Timestamp</th></tr></thead><tbody class="divide-y divide-[#e5e5e7]">
                @forelse ($history as $entry)
                    @php $transaction = $entry->transaction; @endphp
                    <tr class="hover:bg-neutral-100"><td class="px-space-md py-space-sm font-mono-data font-bold text-on-surface">{{ $entry->reference }}</td><td class="px-space-md py-space-sm"><span class="border border-[#e5e5e7] bg-[#f4f4f5] px-space-xs py-0.5 font-label-sm text-label-sm font-bold uppercase text-neutral-700">{{ $entry->movement_type }}</span></td><td class="max-w-[520px] px-space-md py-space-sm">{{ $entry->product_name }} · {{ $entry->variant }} · {{ $entry->size }}<div class="font-mono-data text-xs text-neutral-500">Barcode: {{ $entry->barcode }} · {{ $entry->location }} · {{ $entry->quantity_change > 0 ? '+' : '' }}{{ $entry->quantity_change }} units</div>@if ($transaction && in_array($transaction->type, ['pos_sale', 'offline_sale'], true))<div class="text-xs text-neutral-500">{{ $transaction->payment_method ? strtoupper($transaction->payment_method).' · ' : '' }}₱{{ number_format((float) $transaction->total, 2) }} · {{ strtoupper($transaction->status) }}</div>@elseif ($entry->reason || $entry->notes)<div class="text-xs text-neutral-500">{{ $entry->reason }} {{ $entry->notes }}</div>@endif</td><td class="px-space-md py-space-sm text-neutral-500">{{ $entry->user_name }}</td><td class="whitespace-nowrap px-space-md py-space-sm font-mono-data text-neutral-500">{{ $entry->created_at->timezone('Asia/Manila')->format('Y-m-d H:i:s') }}</td></tr>
                @empty
                    <tr><td colspan="5" class="px-space-md py-space-lg text-center text-neutral-500">No history found.</td></tr>
                @endforelse
            </tbody></table></div>
            @if ($history instanceof \Illuminate\Contracts\Pagination\Paginator || $history instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator)
                <div class="mt-space-md">{{ $history->links() }}</div>
            @endif
        </section>

        <section class="admin-tab-panel {{ $activeAdminTab === 'sale-voids' ? '' : 'hidden' }} w-full flex-col pt-space-lg" id="panel-sale-voids" aria-label="Sale voids">
            <div class="mb-space-md flex flex-col justify-between gap-space-sm border-b border-[#e5e5e7] pb-space-md sm:flex-row sm:items-center"><div><h2 class="font-headline-sm text-headline-sm font-bold uppercase text-on-surface">Sale Voids</h2><p class="mt-1 text-sm text-neutral-500">Void a completed POS or Offline/Warehouse sale. Voiding restores the original stock and keeps the sale in history.</p></div><span class="font-mono-data text-label-sm font-semibold uppercase text-neutral-500">Admin Authorization</span></div>
            <div class="mb-space-xl w-full overflow-x-auto border border-[#e5e5e7] bg-white"><table class="w-full min-w-[950px] border-collapse text-left font-body-sm"><thead><tr class="border-b border-[#e5e5e7] bg-[#f8f8f9] font-label-sm text-label-sm font-bold uppercase text-neutral-700"><th class="px-space-md py-space-sm font-mono-data">Reference / Date</th><th class="px-space-md py-space-sm">Sale</th><th class="px-space-md py-space-sm">Items</th><th class="px-space-md py-space-sm font-mono-data">Amount</th><th class="px-space-md py-space-sm">Status / Void Reason</th><th class="px-space-md py-space-sm text-right">Action</th></tr></thead><tbody class="divide-y divide-[#e5e5e7]">
                @forelse ($transactions->whereIn('type', ['pos_sale', 'offline_sale']) as $transaction)
                    <tr class="hover:bg-neutral-100"><td class="px-space-md py-space-sm font-mono-data font-bold">{{ $transaction->reference }}<span class="mt-1 block font-sans text-xs font-normal text-neutral-500">{{ $transaction->completed_at->timezone('Asia/Manila')->format('M j, Y H:i') }}</span></td><td class="px-space-md py-space-sm">{{ $transaction->type === 'pos_sale' ? 'POS Sale' : 'Offline/Warehouse Sale' }}<span class="mt-1 block text-xs text-neutral-500">{{ $transaction->created_by_name }}</span></td><td class="max-w-80 px-space-md py-space-sm">{{ $transaction->lines->map(fn ($line) => $line->product_name.' · '.$line->variant.' · '.$line->size.' × '.$line->quantity)->implode(', ') }}</td><td class="whitespace-nowrap px-space-md py-space-sm font-mono-data font-bold">₱{{ number_format((float) $transaction->total, 2) }}</td><td class="max-w-60 px-space-md py-space-sm">@if ($transaction->status === 'void')<span class="border border-error bg-red-50 px-space-xs py-0.5 text-xs font-bold text-error">VOID · {{ $transaction->voided_by_name }}</span><span class="mt-1 block text-xs text-neutral-500">{{ $transaction->void_reason }}</span>@else<span class="border border-electric-lime bg-electric-lime/20 px-space-xs py-0.5 text-xs font-bold uppercase">Completed</span>@endif</td><td class="px-space-md py-space-sm text-right">@if ($transaction->status !== 'void')<form class="flex min-w-64 items-center gap-space-xs" method="POST" action="{{ route('admin.void', ['transaction' => $transaction]) }}">@csrf<input class="form-input" name="void_reason" placeholder="Required void reason" maxlength="2000" required><button class="border border-jet-black bg-jet-black px-space-sm py-2 font-label-sm text-label-sm font-bold uppercase text-white transition-colors hover:bg-electric-lime hover:text-jet-black">Void Sale</button></form>@else<span class="text-xs text-neutral-500">Already voided</span>@endif</td></tr>
                @empty
                    <tr><td colspan="6" class="px-space-md py-space-lg text-center text-neutral-500">No POS or Offline/Warehouse sales found.</td></tr>
                @endforelse
            </tbody></table></div>
        </section>
    </div>
</div>

<div id="add-product-modal" class="fixed inset-0 z-[70] {{ $editingProduct || ($errors->any() && old('design_name')) ? 'flex' : 'hidden' }} items-center justify-end bg-black/40" role="dialog" aria-modal="true" aria-labelledby="product-modal-title">
    <div class="flex h-full w-full max-w-lg flex-col justify-between overflow-y-auto border-l border-jet-black bg-white p-space-lg sm:p-space-xl">
        <div><div class="mb-space-lg flex items-center justify-between border-b border-[#e5e5e7] pb-space-md"><div><span class="font-label-sm text-label-sm font-bold uppercase tracking-wider text-neutral-500">Catalog Entry</span><h2 id="product-modal-title" class="font-headline-md text-headline-md font-bold uppercase text-on-surface">{{ $editingProduct ? 'Edit Product' : 'Add New Product' }}</h2></div><button class="p-1 transition-colors hover:bg-[#f4f4f5]" type="button" data-modal-close="add-product-modal" aria-label="Close product form"><span class="material-symbols-outlined text-[24px]">close</span></button></div>
            <form class="space-y-space-md" id="new-product-form" method="POST" action="{{ route('admin.store', ['action' => 'product']) }}" enctype="multipart/form-data">@csrf<input type="hidden" name="id" value="{{ old('id', $editingProduct?->id) }}">
                <label class="form-label">Product Photo<div class="mt-1 flex min-h-28 cursor-pointer flex-col items-center justify-center border border-dashed border-[#e5e5e7] bg-[#f8f8f9] p-space-md transition-colors hover:bg-neutral-100"><span class="material-symbols-outlined mb-1 text-[28px] text-neutral-500">add_photo_alternate</span><span class="font-label-sm text-label-sm font-bold uppercase text-on-surface">Upload Product Photo</span><span class="text-xs font-normal text-neutral-500">JPG, PNG, or WebP · up to 2 MB</span><input class="mt-2 w-full text-xs" type="file" name="photo" accept="image/jpeg,image/png,image/webp"></div></label>
                @if ($editingProduct?->photo_path)<img class="h-20 w-20 border border-[#e5e5e7] object-cover" src="{{ asset('storage/'.$editingProduct->photo_path) }}" alt="Current product photo">@endif
                <label class="form-label">Product Design<input id="product-design-name" class="form-input" name="design_name" value="{{ old('design_name', $editingProduct?->design_name) }}" required></label>
                <div class="grid grid-cols-2 gap-space-md"><label class="form-label">Category<select id="product-category" class="form-input" name="category" required>@foreach (['Court Series','Premier Series','Evolution Series','Accessories'] as $value)<option @selected(old('category', $editingProduct?->category) === $value)>{{ $value }}</option>@endforeach</select></label><label class="form-label">Variant<select id="product-variant" class="form-input" name="variant" required>@foreach (['Unisex Dri-FIT Top','Women’s Dri-FIT Top','Women’s Dri-FIT Tank Top','Accessories'] as $value)<option @selected(old('variant', $editingProduct?->variant) === $value)>{{ $value }}</option>@endforeach</select></label></div>
                @if ($editingProduct)
                    <div class="grid grid-cols-2 gap-space-md"><label class="form-label">Size<select class="form-input font-mono-data" name="size" required>@foreach (['XS','S','M','L','XL','2XL','3XL'] as $value)<option @selected(old('size', $editingProduct->size) === $value)>{{ $value }}</option>@endforeach</select></label><label class="form-label">Barcode<div class="mt-1 flex gap-2"><input id="product-barcode" class="form-input mt-0 min-w-0 font-mono-data" name="barcode" value="{{ old('barcode', $editingProduct->barcode) }}" inputmode="numeric" pattern="[0-9]{8}" maxlength="8" required><button class="scan-barcode shrink-0 border border-[#e5e5e7] bg-[#f4f4f5] px-3 text-xs font-semibold text-on-surface" type="button" data-barcode-target="product-barcode"><span class="material-symbols-outlined align-middle text-base">barcode_scanner</span><span class="ml-1">Scan</span></button></div></label></div>
                @else
                    <fieldset class="border border-[#e5e5e7] p-space-sm"><legend class="px-1 font-label-sm text-label-sm font-bold uppercase tracking-wider">Sizes and Barcodes</legend><p class="mb-2 text-xs text-neutral-500">Select every size to encode. Each size needs its own unique 8-digit barcode.</p><div class="space-y-1" id="product-size-rows">
                        @foreach (['XS','S','M','L','XL','2XL','3XL'] as $value)
                            <div class="product-size-row grid grid-cols-[auto_2.5rem_minmax(0,1fr)_auto] items-center gap-2 border-t border-[#e5e5e7] py-1.5" data-size="{{ $value }}"><input class="product-size-checkbox" type="checkbox" name="sizes[]" value="{{ $value }}" @checked(in_array($value, (array) old('sizes', []), true)) aria-label="Add size {{ $value }}"><span class="font-mono-data text-sm font-bold">{{ $value }}</span><input id="barcode-value-{{ $value }}" class="product-size-barcode form-input mt-0 min-w-0 font-mono-data" name="barcodes[{{ $value }}]" value="{{ old('barcodes.'.$value) }}" inputmode="numeric" pattern="[0-9]{8}" maxlength="8" placeholder="8-digit barcode" disabled aria-label="Barcode for size {{ $value }}"><button class="scan-barcode shrink-0 border border-[#e5e5e7] bg-[#f4f4f5] px-2 py-1 text-xs font-semibold text-on-surface" type="button" data-barcode-target="barcode-value-{{ $value }}"><span class="material-symbols-outlined align-middle text-base">barcode_scanner</span></button></div>
                        @endforeach
                    </div><p id="product-size-help" class="mt-2 text-xs text-neutral-500">Already encoded sizes will be marked and cannot be added again.</p></fieldset>
                    <label class="flex items-center gap-2 text-sm font-semibold"><input type="checkbox" name="eligible" value="1" @checked(old('eligible'))> Eligible for Promo</label>
                @endif
                <label class="form-label">Selling price (₱)<input class="form-input font-mono-data" name="standard_price" type="number" min="0" step="0.01" value="{{ old('standard_price', $editingProduct?->standard_price) }}" required></label>
                <label class="flex items-center justify-between border-t border-[#e5e5e7] pt-space-sm text-sm font-semibold uppercase"><span>Active in catalog</span><input type="checkbox" name="active" value="1" @checked(old('active', $editingProduct?->active ?? true))></label>
                <p class="text-xs text-neutral-500">Set stock through the inventory workflows so every balance change has a matching history record.</p>
                <div class="flex gap-space-md pt-space-sm"><button class="w-1/2 border border-[#e5e5e7] py-space-sm font-label-md text-label-md font-bold uppercase tracking-wider text-neutral-500 transition-colors hover:border-jet-black hover:text-jet-black" type="button" data-modal-close="add-product-modal">Cancel</button><button class="w-1/2 border border-jet-black bg-electric-lime py-space-sm font-label-md text-label-md font-bold uppercase tracking-wider text-jet-black transition-colors hover:bg-jet-black hover:text-electric-lime" type="submit">Save Product</button></div>
            </form>
        </div><div class="border-t border-[#e5e5e7] pt-space-md font-mono-data text-[11px] font-semibold text-neutral-500">BASELINE INVENTORY MANAGEMENT</div>
    </div>
</div>

<div id="user-modal" class="fixed inset-0 z-[70] hidden items-center justify-end bg-black/40" role="dialog" aria-modal="true" aria-labelledby="user-modal-title"><div class="flex h-full w-full max-w-lg flex-col overflow-y-auto border-l border-jet-black bg-white p-space-lg sm:p-space-xl"><div class="mb-space-lg flex items-center justify-between border-b border-[#e5e5e7] pb-space-md"><div><span class="font-label-sm text-label-sm font-bold uppercase tracking-wider text-neutral-500">Staff Access</span><h2 id="user-modal-title" class="font-headline-md text-headline-md font-bold uppercase">Invite User</h2></div><button type="button" data-modal-close="user-modal" aria-label="Close user form"><span class="material-symbols-outlined">close</span></button></div><form id="user-form" class="space-y-space-md" method="POST" action="{{ route('admin.store', ['action' => 'user']) }}">@csrf<input id="user-id" type="hidden" name="id"><label class="form-label">Name<input id="user-name" class="form-input" name="name" required></label><label class="form-label">Username<input id="user-username" class="form-input" name="username" required></label><label class="form-label">System Role<select id="user-role" class="form-input" name="role" required><option value="admin">Admin</option><option value="warehouse_staff">Warehouse Staff</option><option value="pos_staff">POS Staff</option></select></label><label id="user-password-label" class="form-label">Password<input id="user-password" class="form-input" name="password" type="password" minlength="10" required autocomplete="new-password"></label><label class="form-label">Confirm password<input id="user-password-confirmation" class="form-input" name="password_confirmation" type="password" minlength="10" required autocomplete="new-password"></label><label class="flex items-center gap-2 text-sm"><input id="user-active" type="checkbox" name="active" value="1" checked> Active</label><div class="flex gap-space-md pt-space-sm"><button class="w-1/2 border border-[#e5e5e7] py-space-sm font-label-md text-label-md font-bold uppercase" type="button" data-modal-close="user-modal">Cancel</button><button class="w-1/2 border border-jet-black bg-electric-lime py-space-sm font-label-md text-label-md font-bold uppercase">Save User</button></div></form></div></div>



<style>.no-scrollbar::-webkit-scrollbar{display:none}</style>
<script type="application/json" id="admin-product-size-data">{!! $products->map(fn ($product) => [$product->design_name, $product->variant, $product->size])->values()->toJson(JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!}</script>
<script>
const adminTabAliases = {'qr':'qr-settings','voids':'sale-voids'};
function switchAdminTab(tab) {
    const selectedPanel = adminTabAliases[tab] || tab;
    document.querySelectorAll('.admin-tab-panel').forEach(panel => panel.classList.toggle('hidden', panel.id !== `panel-${selectedPanel}`));
    document.querySelectorAll('.admin-tab-button').forEach(button => {
        const active = button.dataset.tab === selectedPanel;
        button.classList.toggle('bg-jet-black', active);
        button.classList.toggle('text-white', active);
        button.classList.toggle('border-b-2', active);
        button.classList.toggle('border-electric-lime', active);
        button.classList.toggle('text-neutral-500', !active);
        button.setAttribute('aria-selected', active ? 'true' : 'false');
    });
    const url = new URL(window.location.href);
    url.searchParams.set('tab', selectedPanel === 'qr-settings' ? 'qr' : selectedPanel === 'sale-voids' ? 'voids' : selectedPanel);
    window.history.replaceState({}, '', url);
}
document.querySelectorAll('.admin-tab-button').forEach(button => button.addEventListener('click', () => switchAdminTab(button.dataset.tab)));
document.querySelectorAll('[data-modal-open]').forEach(button => button.addEventListener('click', () => {
    if (button.hasAttribute('data-user-create')) { document.getElementById('user-form').reset(); document.getElementById('user-id').value = ''; document.getElementById('user-modal-title').textContent = 'Invite User'; document.getElementById('user-password').required = true; document.getElementById('user-password-confirmation').required = true; }
    document.getElementById(button.dataset.modalOpen)?.classList.replace('hidden', 'flex');
}));
document.querySelectorAll('[data-modal-close]').forEach(button => button.addEventListener('click', () => document.getElementById(button.dataset.modalClose)?.classList.replace('flex', 'hidden')));
document.addEventListener('keydown', event => { if (event.key === 'Escape') document.querySelectorAll('[role="dialog"]').forEach(modal => modal.classList.replace('flex', 'hidden')); });
document.querySelectorAll('[data-user-edit]').forEach(button => button.addEventListener('click', () => {
    const form = document.getElementById('user-form');
    form.reset();
    document.getElementById('user-modal-title').textContent = 'Edit User / Reset Password';
    document.getElementById('user-id').value = button.dataset.id;
    document.getElementById('user-name').value = button.dataset.name;
    document.getElementById('user-username').value = button.dataset.username;
    document.getElementById('user-role').value = button.dataset.role;
    document.getElementById('user-active').checked = button.dataset.active === '1';
    document.getElementById('user-password').required = false;
    document.getElementById('user-password-confirmation').required = false;
    document.getElementById('user-modal').classList.replace('hidden', 'flex');
}));
document.getElementById('product-search')?.addEventListener('input', event => {
    const query = event.target.value.trim().toLowerCase();
    let shown = 0;
    document.querySelectorAll('.product-row').forEach(row => { const visible = row.dataset.search.includes(query); row.classList.toggle('hidden', !visible); if (visible) shown++; });
    document.getElementById('product-no-results')?.classList.toggle('hidden', shown > 0);
    document.getElementById('product-filter-state').textContent = query ? 'Filtered Results' : 'All Categories';
});
const adminProductSizeData = JSON.parse(document.getElementById('admin-product-size-data')?.textContent || '[]');
const productDesignInput = document.getElementById('product-design-name');
const productVariantInput = document.getElementById('product-variant');
function syncProductSizeRows() {
    const form = document.getElementById('new-product-form');
    if (!form || form.querySelector('[name="id"]')?.value) return;
    const design = productDesignInput.value.trim().toLocaleLowerCase();
    const variant = productVariantInput.value;
    const existing = new Set(adminProductSizeData.filter(([name, savedVariant]) => name.trim().toLocaleLowerCase() === design && savedVariant === variant).map(([, , size]) => size));
    document.querySelectorAll('.product-size-row').forEach(row => {
        const checkbox = row.querySelector('.product-size-checkbox');
        const barcode = row.querySelector('.product-size-barcode');
        const scan = row.querySelector('.scan-barcode');
        const alreadyEncoded = existing.has(row.dataset.size);
        if (alreadyEncoded) checkbox.checked = false;
        checkbox.disabled = alreadyEncoded;
        barcode.disabled = alreadyEncoded || !checkbox.checked;
        barcode.required = checkbox.checked && !alreadyEncoded;
        barcode.placeholder = alreadyEncoded ? 'Already encoded' : '8-digit barcode';
        scan.disabled = alreadyEncoded || !checkbox.checked;
        scan.classList.toggle('opacity-40', scan.disabled);
        row.classList.toggle('opacity-50', alreadyEncoded);
    });
}
document.querySelectorAll('.product-size-checkbox').forEach(checkbox => checkbox.addEventListener('change', syncProductSizeRows));
productDesignInput?.addEventListener('input', syncProductSizeRows);
productVariantInput?.addEventListener('change', syncProductSizeRows);
syncProductSizeRows();
document.querySelectorAll('.scan-barcode').forEach(button => button.addEventListener('click', () => {
    const barcode = document.getElementById(button.dataset.barcodeTarget);
    if (!barcode || barcode.disabled) return;
    barcode.focus();
    barcode.select();
}));
document.querySelectorAll('#new-product-form [inputmode="numeric"]').forEach(barcode => barcode.addEventListener('input', event => { const input = event.currentTarget; input.value = input.value.replace(/\D/g, '').slice(0, 8); }));
document.querySelectorAll('.product-promotion-form').forEach(form => {
    const checkbox = form.querySelector('.product-promo-eligible');
    checkbox.addEventListener('change', () => form.requestSubmit());
});
function exportTable(tableId, fileName) { const table = document.getElementById(tableId); if (!table) return; const rows = [...table.querySelectorAll('tr')].filter(row => !row.classList.contains('hidden')); const csv = rows.map(row => [...row.querySelectorAll('th,td')].map(cell => '"'+cell.innerText.replaceAll('"','""').trim()+'"').join(',')).join('\r\n'); const link = document.createElement('a'); link.href = URL.createObjectURL(new Blob([csv], {type:'text/csv'})); link.download = fileName; link.click(); URL.revokeObjectURL(link.href); }
@if ($errors->any() && old('design_name'))
document.addEventListener('DOMContentLoaded', () => switchAdminTab('products'));
@endif
</script>
