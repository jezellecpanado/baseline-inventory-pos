@extends('layouts.app')

@section('title', 'Point of Sale')

@section('content')
@php
    $productGroups = $products->groupBy(fn ($product) => $product->design_name.'|'.$product->variant);
@endphp
<div class="mx-auto flex min-h-[calc(100vh-4rem)] w-full max-w-[1600px] flex-col px-gutter py-space-md sm:px-gutter-tablet lg:px-gutter-desktop">
    <div class="flex flex-wrap items-center justify-between gap-space-md border-b border-surface-variant pb-space-md">
        <div class="flex items-center gap-space-xs bg-jet-black px-space-sm py-space-xs text-white"><span class="h-2 w-2 rounded-full bg-electric-lime"></span><span class="font-label-sm text-label-sm uppercase tracking-wider">POS Stock Mode: Online</span></div>
        <div class="flex items-center gap-space-sm border border-surface-variant border-l-4 border-l-jet-black bg-white px-space-md py-space-xs shadow-sm"><span class="material-symbols-outlined text-[18px] text-jet-black">verified</span><span class="font-label-sm text-label-sm font-semibold uppercase tracking-widest text-on-surface">Exchange Only — No Cash/Card Refunds</span></div>
    </div>

    <div class="grid grid-cols-5 gap-space-xs border-b border-surface-variant py-space-md text-center">
        @foreach (['Scan / Select', 'Set Quantity', 'Stock Check', 'Touch Pay', 'Issue Ref #'] as $step)
            <div class="py-space-xs {{ $loop->first ? 'bg-jet-black text-white' : 'bg-surface-container text-secondary' }} font-label-sm text-label-sm uppercase tracking-wider">{{ $loop->iteration }}. {{ $step }}</div>
        @endforeach
    </div>

    <div class="grid flex-1 grid-cols-1 items-start gap-space-md pt-space-md lg:grid-cols-12">
        <section class="min-w-0 lg:col-span-7" aria-label="POS product selection">
            <form id="barcode-form" class="flex gap-space-sm" onsubmit="return false">
                <div class="relative min-w-0 flex-1">
                    <span class="material-symbols-outlined pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-secondary">barcode_scanner</span>
                    <input id="barcodeInput" autofocus class="h-12 w-full border-2 border-surface-variant bg-white pl-12 pr-10 font-mono-data text-body-lg outline-none focus:border-jet-black" type="text" inputmode="numeric" pattern="[0-9]{8}" maxlength="8" placeholder="Scan 8-digit barcode (e.g. 84920193)..." autocomplete="off" aria-label="Scan or enter an 8-digit barcode">
                    <button class="absolute inset-y-0 right-0 flex items-center pr-space-md text-secondary hover:text-jet-black" onclick="document.getElementById('barcodeInput').value = ''; document.getElementById('barcodeInput').focus()" type="button" aria-label="Clear barcode"><span class="material-symbols-outlined text-[18px]">close</span></button>
                </div>
                <button id="openCatalogButton" class="flex h-12 shrink-0 items-center justify-center gap-space-xs bg-jet-black px-space-lg font-label-md text-label-md uppercase tracking-wider text-white shadow-sm transition hover:bg-neutral-700" type="button"><span class="material-symbols-outlined text-[18px]">add_circle</span><span>Add product</span></button>
            </form>
            <p id="pos-message" class="min-h-6 pt-1 text-xs text-error" role="status" aria-live="polite"></p>

            <label class="mb-space-sm block"><span class="sr-only">Search exact product</span><input id="catalog-search" class="form-input mt-0" type="search" placeholder="Search product, variant, size, or barcode" autocomplete="off"></label>
            <div class="mb-space-sm flex items-center gap-space-xs overflow-x-auto border-b border-surface-variant pb-space-xs" role="group" aria-label="Product categories">
                @foreach (['All', 'Court Series', 'Premier Series', 'Evolution Series', 'Accessories'] as $category)
                    <button class="cat-pill shrink-0 border px-space-md py-space-xs font-label-sm text-label-sm uppercase tracking-wider transition {{ $loop->first ? 'border-jet-black bg-jet-black text-white' : 'border-surface-variant bg-white text-on-surface hover:bg-surface-container' }}" type="button" data-category="{{ $loop->first ? '' : $category }}">{{ $category }}</button>
                @endforeach
            </div>

            <div id="catalogGrid" class="grid max-h-[calc(100vh-27rem)] grid-cols-2 content-start gap-space-sm overflow-y-auto pr-1 md:grid-cols-3" aria-live="polite"></div>
            <p id="catalog-empty" class="hidden border border-dashed border-surface-variant p-space-md text-center text-sm text-secondary">Search by product, variant, size, or barcode.</p>
        </section>

        <section class="flex min-w-0 flex-col border border-surface-variant bg-white p-space-md shadow-sm lg:sticky lg:top-20 lg:col-span-5 lg:max-h-[calc(100vh-6rem)]" aria-label="Active POS register">
            <div class="flex min-w-0 flex-wrap items-center justify-between gap-space-xs border-b-2 border-jet-black pb-space-sm">
                <div class="flex min-w-0 items-center gap-space-xs"><span class="material-symbols-outlined shrink-0 text-[22px]">point_of_sale</span><h2 class="truncate font-headline-sm text-headline-sm font-bold uppercase">Active Register</h2></div>
                <div class="flex min-w-0 flex-wrap items-center justify-end gap-1">
                    <button class="pos-panel-button border border-surface-variant bg-surface-container px-space-sm py-1 font-label-sm uppercase" type="button" data-modal-open="exchange-modal">Exchange Item</button>
                    <button class="pos-panel-button border border-surface-variant bg-surface-container px-space-sm py-1 font-label-sm uppercase" type="button" data-modal-open="history-modal">History</button>
                    <button class="pos-panel-button border border-surface-variant bg-surface-container px-space-sm py-1 font-label-sm uppercase" type="button" data-modal-open="pos-inventory-modal">POS Inventory</button>
                    @if ($user->isAdmin())<button class="pos-panel-button border border-surface-variant bg-surface-container px-space-sm py-1 font-label-sm uppercase" type="button" data-modal-open="pos-withdrawal-modal">Withdrawal</button>@endif
                </div>
            </div>
            <div id="cart-container" class="my-space-sm flex min-h-[130px] flex-1 flex-col gap-space-xs overflow-y-auto lg:min-h-0">
                <h3 id="selected-products-heading" class="hidden font-label-sm text-label-sm uppercase tracking-wider text-secondary">Selected Products</h3>
                <div class="flex h-48 flex-col items-center justify-center text-center text-secondary" id="cartEmpty"><span class="material-symbols-outlined text-4xl">barcode</span><p class="mt-2 font-semibold text-jet-black">Cart Empty</p><p class="mt-1 text-xs">Scan a barcode or select a product.</p></div>
                <div class="hidden flex-col gap-space-xs" id="cartItemsList"></div>
            </div>
            <button id="addAnotherPosProduct" class="mb-space-sm hidden w-full border border-surface-variant bg-white px-space-sm py-2 text-xs font-semibold uppercase tracking-wider hover:bg-surface-container" type="button">Add Another Product</button>
            <div id="promo-callout" class="mb-space-sm hidden items-center justify-between border-l-4 border-electric-lime bg-surface-container px-space-sm py-space-xs text-xs"><span class="flex items-center gap-1"><span class="material-symbols-outlined text-sm">local_offer</span><span id="promo-callout-name" class="font-semibold">Buy 2 for ₱899 applied</span></span><span id="promo-callout-discount" class="font-bold"></span></div>
            <div id="stock-error-banner" class="mb-space-sm hidden items-center gap-space-xs border border-error bg-red-50 p-space-sm text-xs text-error" role="alert"><span class="material-symbols-outlined">warning</span><span id="stock-error-text"></span></div>
            <div id="checkout-form" class="mt-space-sm space-y-space-xs border border-surface-variant bg-surface-container p-space-md">
                <div class="flex items-center justify-between text-sm text-secondary"><span>Subtotal</span><span id="subtotalVal" class="font-semibold text-jet-black">₱0.00</span></div>
                <div class="flex items-center justify-between text-sm text-secondary"><span>Promo Discount</span><span id="discountVal" class="shrink-0">-₱0.00</span></div>
                <div class="flex items-center justify-between text-xs text-secondary" id="cartItemCountLine"><span>Items:</span><span id="cartItemCount" class="font-mono-data font-semibold">0</span></div>
                <div class="flex items-baseline justify-between border-t-2 border-jet-black pt-space-xs"><span class="font-headline-sm text-headline-sm font-bold uppercase tracking-wider">Grand Total</span><span id="grandTotalVal" class="font-mono-data text-2xl font-bold">₱0.00</span></div>
                <div class="grid grid-cols-2 gap-space-sm pt-space-sm">
                    <button id="payCash" class="flex h-16 min-h-14 flex-col items-center justify-center gap-0.5 bg-jet-black px-space-sm py-space-sm font-label-lg text-label-lg uppercase tracking-wider text-white shadow-sm transition hover:bg-neutral-700 disabled:cursor-not-allowed disabled:opacity-40" type="button"><span class="flex items-center gap-1 font-bold"><span class="material-symbols-outlined">payments</span>Cash</span><span class="font-mono-data text-[10px] text-neutral-300">Tap to tender</span></button>
                    <button id="payQr" class="flex h-16 min-h-14 flex-col items-center justify-center gap-0.5 border-2 border-jet-black bg-electric-lime px-space-sm py-space-sm font-label-lg text-label-lg uppercase tracking-wider text-jet-black shadow-sm transition hover:bg-electric-lime-hover disabled:cursor-not-allowed disabled:opacity-40" type="button" title="{{ $qrImage ? 'Collect payment by QR' : 'Configure the Baseline QR image in Admin to accept QR payments' }}" aria-label="{{ $qrImage ? 'Pay by QR' : 'QR payment unavailable until a QR image is configured' }}" {{ $qrImage ? '' : 'disabled' }}><span class="flex items-center gap-1 font-bold"><span class="material-symbols-outlined">qr_code_scanner</span>QR Digital</span><span class="font-mono-data text-[10px] font-semibold">Instant touch pay</span></button>
                </div>
                <div class="flex items-center justify-between gap-2 pt-space-sm"><button id="resetCart" class="flex items-center gap-1 text-xs font-semibold uppercase tracking-wider text-secondary transition hover:text-error" type="button"><span class="material-symbols-outlined text-base">delete_sweep</span>Clear Register</button><span class="font-mono-data text-[10px] text-secondary">Terminal: POS-T01-FLR</span></div>
                <form id="checkout-form-submit" method="POST" action="{{ route('pos.sale') }}" class="hidden">
                    @csrf
                    <input type="hidden" name="submission_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                    <input id="payment-method" type="hidden" name="payment_method" value="cash">
                    <input id="qr-confirmed" type="hidden" name="qr_confirmed" value="">
                    <div id="checkout-items"></div>
                </form>
            </div>
        </section>
    </div>
</div>

<div id="qrModal" class="fixed inset-0 z-[70] hidden items-center justify-center bg-black/40 p-4" role="dialog" aria-modal="true" aria-labelledby="qrModalTitle"><div class="w-full max-w-md rounded-2xl bg-white p-space-lg shadow-xl"><div class="flex items-center justify-between"><h2 id="qrModalTitle" class="font-headline-sm text-headline-sm font-bold">QR Payment</h2><button type="button" data-modal-close="qrModal" class="rounded-lg p-2 text-secondary hover:bg-surface-container" aria-label="Close"><span class="material-symbols-outlined">close</span></button></div><p class="mt-1 text-sm text-secondary">Scan the displayed QR code and confirm the payment before completing the sale.</p><div class="mt-space-md flex items-center justify-between bg-surface-container-low px-space-md py-space-sm"><span class="text-sm text-secondary">Amount to pay</span><strong id="qrTotal" class="text-lg font-bold">₱0.00</strong></div><div class="my-space-md flex min-h-48 items-center justify-center rounded-xl border border-surface-container bg-white p-4">@if($qrImage)<img class="max-h-64 max-w-full object-contain" src="{{ asset('storage/'.$qrImage) }}" alt="Baseline QR payment code">@else<p class="text-sm text-secondary">QR payment is not configured.</p>@endif</div><label class="flex items-start gap-2 text-sm"><input id="qrPaymentConfirmed" type="checkbox" class="mt-1"><span>I confirm that the QR payment was received.</span></label><button id="confirmQrPayment" class="mt-space-md w-full rounded-lg bg-inverse-surface px-4 py-3 font-semibold text-white disabled:cursor-not-allowed disabled:opacity-40" type="button" {{ $qrImage ? '' : 'disabled' }}>Confirm & Complete Sale</button></div></div>
<div id="cashModal" class="fixed inset-0 z-[70] hidden items-center justify-center bg-black/60 p-4" role="dialog" aria-modal="true" aria-labelledby="cashModalTitle">
    <div class="w-full max-w-md border-2 border-jet-black bg-white p-space-lg shadow-xl sm:p-space-xl">
        <div class="flex items-center justify-between border-b-2 border-jet-black pb-space-sm">
            <div><p class="font-label-sm text-label-sm uppercase tracking-wider text-secondary">Cash Checkout</p><h2 id="cashModalTitle" class="mt-1 font-headline-md text-headline-md font-bold">Collect payment</h2></div>
            <button type="button" data-modal-close="cashModal" class="rounded-lg p-2 text-secondary hover:bg-surface-container" aria-label="Close cash checkout"><span class="material-symbols-outlined">close</span></button>
        </div>
        <div class="py-space-md">
            <div class="flex items-center justify-between"><span class="text-sm text-secondary">Total due</span><strong id="cashTotal" class="text-2xl font-bold">₱0.00</strong></div>
            <label for="cashReceived" class="mt-space-md block text-sm font-semibold">Cash Received</label>
            <div class="mt-1 flex items-center border-2 border-jet-black px-space-sm"><span class="text-lg font-semibold">₱</span><input id="cashReceived" class="h-14 w-full border-0 bg-transparent px-space-sm text-2xl font-bold outline-none" type="text" inputmode="decimal" autocomplete="off" aria-describedby="cashChangeHint"></div>
            <div class="mt-space-sm"><p class="mb-1 text-[10px] font-semibold uppercase tracking-wider text-secondary">Quick amount</p><div id="cashQuickAmounts" class="grid grid-cols-4 gap-space-xs"></div></div>
            <div class="mt-space-md flex items-center justify-between border-t border-surface-variant pt-space-md"><span class="text-sm font-semibold">Change</span><strong id="cashChange" class="text-3xl font-bold text-jet-black">₱0.00</strong></div>
            <p id="cashChangeHint" class="mt-1 text-xs text-secondary" aria-live="polite"></p>
        </div>
        <button id="completeCashSale" type="button" class="w-full bg-jet-black px-space-md py-4 font-label-lg text-label-lg uppercase tracking-wider text-white transition hover:bg-neutral-700 disabled:cursor-not-allowed disabled:opacity-40">Complete Cash Sale</button>
    </div>
</div>

<div id="pos-inventory-modal" class="fixed inset-0 z-[60] hidden items-center justify-center bg-black/40 p-3 sm:p-4" role="dialog" aria-modal="true" aria-labelledby="posInventoryTitle">
    <div class="max-h-[90vh] w-full max-w-5xl overflow-hidden bg-white shadow-xl">
        <div class="flex items-center justify-between border-b border-surface-variant px-4 py-3 sm:px-space-lg sm:py-space-md">
            <div><h2 id="posInventoryTitle" class="font-headline-sm text-headline-sm font-bold">POS Inventory</h2><p class="mt-0.5 text-xs text-secondary">Stock on the POS floor, grouped by product and variant</p></div>
            <button type="button" data-modal-close="pos-inventory-modal" class="rounded-lg p-2 text-secondary transition hover:bg-surface-container-low" aria-label="Close POS Inventory"><span class="material-symbols-outlined">close</span></button>
        </div>
        <div class="max-h-[calc(90vh-4.5rem)] overflow-y-auto p-3 sm:p-space-md">
            @php $inventoryGroups = $products->groupBy('design_name'); @endphp
            @forelse ($inventoryGroups as $design => $designProducts)
                @foreach ($designProducts->groupBy('variant') as $variant => $variantProducts)
                    @php
                        $photoProduct = $variantProducts->first(fn ($product) => filled($product->photo_path)) ?? $variantProducts->first();
                        $orderedSizes = $variantProducts->sortBy(fn ($product) => array_search($product->size, ['XS', 'S', 'M', 'L', 'XL', '2XL', '3XL'], true) === false ? 99 : array_search($product->size, ['XS', 'S', 'M', 'L', 'XL', '2XL', '3XL'], true));
                    @endphp
                    <article class="mb-space-sm overflow-hidden border border-surface-variant bg-white">
                        <div class="flex items-center gap-space-sm border-b border-surface-variant bg-surface-container-low p-space-sm">
                            @if ($photoProduct?->photo_path)
                                <img class="h-16 w-16 shrink-0 object-contain sm:h-20 sm:w-20" src="{{ asset('storage/'.$photoProduct->photo_path) }}" alt="{{ $design }} — {{ $variant }}" loading="lazy">
                            @else
                                <div class="flex h-16 w-16 shrink-0 items-center justify-center bg-surface-container text-secondary sm:h-20 sm:w-20"><span class="material-symbols-outlined text-3xl">image</span></div>
                            @endif
                            <div class="min-w-0"><p class="truncate font-semibold">{{ $design }}</p><p class="truncate text-sm text-secondary">{{ $variant }}</p><p class="mt-1 text-[10px] uppercase tracking-wider text-secondary">{{ $variantProducts->count() }} {{ $variantProducts->count() === 1 ? 'size' : 'sizes' }} · POS stock only</p></div>
                        </div>
                        <div class="grid grid-cols-2 gap-2 p-space-sm sm:grid-cols-3 lg:grid-cols-4">
                            @foreach ($orderedSizes as $product)
                                @php $posQuantity = (int) ($product->balance?->pos_quantity ?? 0); @endphp
                                <div class="flex min-w-0 items-center justify-between gap-1 border border-surface-variant bg-surface-container-low px-2 py-1.5" aria-label="{{ $product->size }} size, {{ $posQuantity }} POS stock, barcode {{ $product->barcode }}">
                                    <div class="min-w-0"><span class="block text-xs font-semibold">{{ $product->size }}</span><span class="block truncate font-mono text-[9px] text-secondary">{{ $product->barcode }}</span></div>
                                    <span class="min-w-8 shrink-0 bg-white px-1.5 py-1 text-center text-xs font-bold {{ $posQuantity > 0 ? 'text-jet-black' : 'text-secondary' }}">{{ number_format($posQuantity) }}</span>
                                </div>
                            @endforeach
                        </div>
                    </article>
                @endforeach
            @empty
                <div class="border border-dashed border-surface-variant p-space-lg text-center text-sm text-secondary">No active POS products.</div>
            @endforelse
        </div>
    </div>
</div>

<div id="history-modal" class="fixed inset-0 z-[60] hidden items-center justify-center bg-jet-black/80 p-3 sm:p-space-md" role="dialog" aria-modal="true" aria-labelledby="posHistoryTitle">
    <div class="flex max-h-[85vh] w-full min-w-0 max-w-4xl flex-col overflow-hidden border-2 border-jet-black bg-white p-space-md shadow-2xl sm:p-space-xl">
        <div class="mb-space-md flex shrink-0 items-center justify-between gap-3 border-b-2 border-jet-black pb-space-sm"><h2 id="posHistoryTitle" class="flex items-center gap-space-xs font-headline-sm text-headline-sm font-bold uppercase"><span class="material-symbols-outlined">history</span>Recent Shift Transactions</h2><button type="button" data-modal-close="history-modal" class="shrink-0 p-2 text-secondary hover:text-on-surface" aria-label="Close"><span class="material-symbols-outlined">close</span></button></div>
        <div class="min-h-0 w-full min-w-0 max-w-full overflow-y-auto p-space-md">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[640px] text-left font-mono-data text-body-sm">
                    <thead><tr class="border-b-2 border-jet-black font-label-sm text-label-sm uppercase text-secondary"><th class="py-2">Ref #</th><th class="py-2">Time</th><th class="py-2">Items</th><th class="py-2">Tender</th><th class="py-2 text-right">Total</th></tr></thead>
                    <tbody class="divide-y divide-surface-variant">
                        @forelse($transactions as $transaction)
                            <tr><td class="max-w-36 break-all py-2 font-semibold">{{ $transaction->reference }}</td><td class="whitespace-nowrap py-2 text-secondary">{{ $transaction->completed_at->timezone('Asia/Manila')->format('M j, g:i A') }}</td><td class="max-w-72 whitespace-normal py-2">{{ $transaction->lines->map(fn ($line) => $line->quantity.'× '.$line->product_name.' · '.$line->variant.' · '.$line->size)->implode(', ') ?: ($transaction->reason ?: ucfirst($transaction->type)) }}</td><td class="py-2">{{ $transaction->payment_method ? strtoupper($transaction->payment_method) : '—' }}</td><td class="whitespace-nowrap py-2 text-right font-bold text-jet-black">{{ $transaction->type==='withdrawal' ? '—' : '₱'.number_format((float) $transaction->total,2) }}</td></tr>
                        @empty
                            <tr><td colspan="5" class="py-8 text-center text-secondary">No POS transactions yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

@if ($user->isAdmin())
<div id="pos-withdrawal-modal" class="fixed inset-0 z-[60] hidden items-center justify-center bg-black/40 p-3 sm:p-4" role="dialog" aria-modal="true" aria-labelledby="posWithdrawalTitle">
    <div class="max-h-[92vh] w-full max-w-2xl overflow-y-auto rounded-2xl bg-white shadow-xl">
        <div class="sticky top-0 z-10 flex items-center justify-between border-b border-surface-container bg-surface-container-low px-4 py-3 sm:px-space-lg sm:py-space-md">
            <div class="flex items-center gap-2"><span class="material-symbols-outlined text-primary">output</span><div><h2 id="posWithdrawalTitle" class="font-headline-sm text-headline-sm font-bold">POS Withdrawal</h2><p class="text-xs text-secondary">Remove items from POS stock.</p></div></div>
            <button type="button" data-modal-close="pos-withdrawal-modal" class="rounded-lg p-2 text-secondary hover:bg-surface-container" aria-label="Close withdrawal"><span class="material-symbols-outlined">close</span></button>
        </div>
        <form id="pos-withdrawal-form" class="space-y-4 p-4 sm:p-space-lg" method="POST" action="{{ route('pos.withdrawal') }}">
            @csrf
            <input type="hidden" name="submission_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
            <div class="space-y-3 rounded-xl border border-surface-container p-3 sm:p-4">
                <div><h3 class="text-sm font-bold">Select product</h3><p class="mt-0.5 text-xs text-secondary">Scan a barcode or choose from the product list.</p></div>
                <div class="flex gap-2"><input class="form-input mt-0" id="pos-withdrawal-barcode" type="text" inputmode="numeric" maxlength="8" placeholder="Scan barcode" aria-label="Scan withdrawal product barcode"><button type="button" id="pos-withdrawal-scan" class="rounded-lg bg-inverse-surface px-3 py-2 text-xs font-semibold text-white">Scan</button></div>
                <label class="form-label">Manual Product Selection<select id="pos-withdrawal-product-select" class="form-input"><option value="">Select Product + Variant + Size + Barcode</option>@foreach ($products as $product)<option value="{{ $product->id }}" @if (($product->balance?->pos_quantity ?? 0) < 1) disabled @endif>{{ $product->design_name }} · {{ $product->variant }} · {{ $product->size }} · Barcode: {{ $product->barcode }}</option>@endforeach</select></label>
                <p id="pos-withdrawal-error" class="hidden rounded-lg bg-error/10 p-2 text-sm text-error" role="alert"></p>
            </div>
            <section class="rounded-xl border border-surface-container p-3 sm:p-4" aria-labelledby="posWithdrawalItemsTitle">
                <div class="mb-2 flex items-center justify-between"><h3 id="posWithdrawalItemsTitle" class="text-sm font-bold">Selected Products</h3><span id="pos-withdrawal-count" class="text-xs text-secondary">0 items</span></div>
                <div id="pos-withdrawal-list" class="divide-y divide-surface-container"></div>
                <p id="pos-withdrawal-list-empty" class="py-4 text-center text-xs text-secondary">No products selected.</p>
                <button id="pos-withdrawal-add-another" class="hidden w-full border-t border-surface-container py-2 text-xs font-semibold hover:bg-surface-container-low" type="button">Add Another Product</button>
                <div id="pos-withdrawal-items"></div>
            </section>
            <label class="form-label">Reason<select id="pos-withdrawal-reason" class="form-input" name="reason" required><option value="">Select reason</option><option>Freebies</option><option>Giveaways</option><option>Sponsorship</option><option>Marketing</option><option>Samples</option><option>Damaged Items</option><option>Others</option></select></label>
            <label id="pos-withdrawal-notes-wrap" class="form-label hidden" style="display:none">Notes for Others<textarea id="pos-withdrawal-notes" class="form-input" name="notes" rows="2" maxlength="2000" placeholder="Add notes"></textarea></label>
            <button id="pos-withdrawal-submit" class="w-full rounded-lg bg-inverse-surface px-4 py-3 font-semibold text-white disabled:cursor-not-allowed disabled:opacity-40" type="submit" disabled>Complete Withdrawal</button>
        </form>
    </div>
</div>
@endif

<div id="exchange-modal" class="fixed inset-0 z-[60] hidden items-center justify-center bg-black/40 p-3 sm:p-4" role="dialog" aria-modal="true" aria-labelledby="exchangeTitle">
    <div class="max-h-[92vh] w-full max-w-3xl overflow-y-auto rounded-2xl bg-white shadow-xl">
        <div class="sticky top-0 z-10 flex items-center justify-between border-b border-surface-container bg-surface-container-low px-4 py-3 sm:px-space-lg sm:py-space-md">
            <div class="flex items-center gap-2"><span class="material-symbols-outlined text-primary">swap_horiz</span><h2 id="exchangeTitle" class="font-headline-sm text-headline-sm font-bold">POS Exchange</h2></div>
            <button type="button" data-modal-close="exchange-modal" class="rounded-lg p-2 text-secondary hover:bg-surface-container" aria-label="Close"><span class="material-symbols-outlined">close</span></button>
        </div>
        <form id="exchange-form" class="space-y-4 p-4 sm:p-space-lg" method="POST" action="{{ route('pos.exchange') }}">
            @csrf<input type="hidden" name="submission_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}"><input type="hidden" name="location" value="pos">
            <input type="hidden" name="returned_product_id" id="exchange-returned-id"><input type="hidden" name="replacement_product_id" id="exchange-replacement-id">
            <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                <section class="space-y-3 rounded-xl border border-surface-container p-3 sm:p-4">
                    <div><h3 class="text-sm font-bold">Returned product</h3><p class="mt-0.5 text-xs text-secondary">Scan its barcode or choose from the product list.</p></div>
                    <div class="flex gap-2"><input class="form-input mt-0" id="exchange-returned-barcode" type="text" inputmode="numeric" maxlength="8" placeholder="Scan barcode" aria-label="Scan returned product barcode"><button type="button" class="exchange-scan rounded-lg bg-inverse-surface px-3 py-2 text-xs font-semibold text-white" data-target="returned">Scan</button></div>
                    <label class="form-label">Manual Product Selection<select id="exchange-returned-select" class="form-input"><option value="">Select Product + Variant + Size + Barcode</option>@foreach ($products as $product)<option value="{{ $product->id }}">{{ $product->design_name }} · {{ $product->variant }} · {{ $product->size }} · Barcode: {{ $product->barcode }}</option>@endforeach</select></label><p id="exchange-returned-summary" class="text-sm text-secondary">No product selected.</p>
                    <p id="exchange-returned-stock" class="text-xs text-secondary">Select a returned product to see available POS stock.</p>
                    <label class="form-label">Returned quantity<input class="form-input" id="exchange-return-qty" type="number" name="returned_quantity" min="1" value="1" required disabled></label>
                </section>
                <section class="space-y-3 rounded-xl border border-surface-container p-3 sm:p-4">
                    <div><h3 class="text-sm font-bold">Replacement product</h3><p class="mt-0.5 text-xs text-secondary">Scan its barcode or choose from the product list.</p></div>
                    <div class="flex gap-2"><input class="form-input mt-0" id="exchange-replacement-barcode" type="text" inputmode="numeric" maxlength="8" placeholder="Scan barcode" aria-label="Scan replacement product barcode"><button type="button" class="exchange-scan rounded-lg bg-inverse-surface px-3 py-2 text-xs font-semibold text-white" data-target="replacement">Scan</button></div>
                    <label class="form-label">Manual Product Selection<select id="exchange-replacement-select" class="form-input"><option value="">Select Product + Variant + Size + Barcode</option>@foreach ($products as $product)<option value="{{ $product->id }}" @if (($product->balance?->pos_quantity ?? 0) < 1) disabled @endif>{{ $product->design_name }} · {{ $product->variant }} · {{ $product->size }} · Barcode: {{ $product->barcode }}</option>@endforeach</select></label><p id="exchange-replacement-summary" class="text-sm text-secondary">No product selected.</p>
                    <p id="exchange-replacement-stock" class="text-xs text-secondary">Select a replacement to see available POS stock.</p>
                    <label class="form-label">Replacement quantity<input class="form-input" id="exchange-replacement-qty" type="number" name="replacement_quantity" min="1" value="1" required disabled></label>
                </section>
            </div>
            <label class="form-label">Condition of returned product<select class="form-input" name="condition" required><option value="sellable">Sellable</option><option value="damaged">Damaged</option></select></label>
            <div class="rounded-lg bg-surface-container-low p-3 text-sm"><span class="text-secondary">Difference to collect:</span> <strong id="exchange-difference">₱0.00</strong><p class="mt-1 text-xs text-secondary">If the replacement costs less, no refund is issued and the difference is forfeited.</p></div>
            <div id="exchange-payment" class="hidden space-y-2 rounded-lg border border-surface-container p-3"><label class="form-label">Payment method<select class="form-input" name="payment_method"><option value="cash">Cash</option><option value="qr" {{ $qrImage ? '' : 'disabled' }}>QR</option></select></label><label class="flex items-center gap-2 text-sm"><input type="checkbox" name="qr_confirmed" value="1"> QR payment received and confirmed</label>@if($qrImage)<img class="max-h-40 rounded-lg border border-surface-container p-2" src="{{ asset('storage/'.$qrImage) }}" alt="Baseline QR payment code">@endif</div>
            <p id="exchange-message" class="hidden rounded-lg bg-error/10 p-2 text-sm text-error" role="alert"></p>
            <button id="completeExchange" class="w-full rounded-lg bg-inverse-surface px-4 py-3 font-semibold text-white disabled:cursor-not-allowed disabled:opacity-40" type="submit">Complete Exchange</button>
        </form>
    </div>
</div>

<script>
const posProducts = {{ \Illuminate\Support\Js::from($products->map(fn ($product) => ['id'=>$product->id,'name'=>$product->design_name,'category'=>$product->category,'variant'=>$product->variant,'size'=>$product->size,'barcode'=>$product->barcode,'price'=>(float)$product->standard_price,'pos'=>(int)($product->balance?->pos_quantity ?? 0),'photo'=>$product->photo_path ? asset('storage/'.$product->photo_path) : null,'promotion_id'=>$product->promotion?->active && $product->promotion->required_quantity === 2 && (float) $product->promotion->bundle_price === 899.0 ? $product->promotion_id : null])->values()) }};
let cart = {};
const byId = id => document.getElementById(id);
const money = value => '₱' + Number(value).toLocaleString('en-PH', {minimumFractionDigits:2, maximumFractionDigits:2});
function escapeHtml(value){return String(value).replace(/[&<>"']/g,char=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char]));}
function message(text=''){byId('pos-message').textContent=text;const banner=byId('stock-error-banner');banner.classList.toggle('hidden',!text);byId('stock-error-text').textContent=text;}
function showModal(id){const modal=byId(id);modal.classList.remove('hidden');modal.classList.add('flex');}
function hideModal(id){const modal=byId(id);modal.classList.add('hidden');modal.classList.remove('flex');}
document.querySelectorAll('[data-modal-open]').forEach(button=>button.addEventListener('click',()=>showModal(button.dataset.modalOpen)));
document.querySelectorAll('[data-modal-close]').forEach(button=>button.addEventListener('click',()=>hideModal(button.dataset.modalClose)));
document.addEventListener('keydown',event=>{if(event.key==='Escape'){document.querySelectorAll('[role=dialog]').forEach(modal=>{if(!modal.classList.contains('hidden'))hideModal(modal.id);});}});
byId('catalog-search')?.addEventListener('input',filterCatalog);
document.querySelectorAll('.cat-pill').forEach(button=>button.addEventListener('click',()=>{document.querySelectorAll('.cat-pill').forEach(pill=>pill.className='cat-pill shrink-0 border border-surface-variant bg-white px-space-md py-space-xs font-label-sm text-label-sm uppercase tracking-wider text-on-surface transition hover:bg-surface-container');button.className='cat-pill shrink-0 border border-jet-black bg-jet-black px-space-md py-space-xs font-label-sm text-label-sm uppercase tracking-wider text-white transition';filterCatalog();}));
byId('openCatalogButton')?.addEventListener('click',()=>{if(byId('barcodeInput').value.trim()){clearTimeout(barcodeScanTimer);handleBarcode();return;}byId('catalog-search').focus();byId('catalogGrid').scrollIntoView({behavior:'smooth',block:'start'});});
const catalogSelection={groupId:null,productId:null,quantity:1};
function renderPosProductChoices(){
    const category=document.querySelector('.cat-pill.border-jet-black')?.dataset.category||'';
    const query=byId('catalog-search')?.value.trim().toLowerCase()||'';
    const grouped=new Map();
    posProducts.filter(product=>!category||product.category===category).forEach(product=>{
        const key=`${product.name}\u0000${product.variant}`;
        if(!grouped.has(key))grouped.set(key,{id:product.id,name:product.name,variant:product.variant,category:product.category,photo:product.photo,products:[]});
        const group=grouped.get(key);group.products.push(product);if(!group.photo&&product.photo)group.photo=product.photo;
    });
    const matches=[...grouped.values()].filter(group=>!query||`${group.name} ${group.variant} ${group.products.map(item=>`${item.size} ${item.barcode}`).join(' ')}`.toLowerCase().includes(query));
    const list=byId('catalogGrid');
    list.innerHTML=matches.map(group=>{
        const expanded=catalogSelection.groupId===group.id;
        const image=group.photo?`<img class="h-full w-full object-contain" src="${escapeHtml(group.photo)}" alt="">`:'<span class="material-symbols-outlined text-3xl text-secondary">image</span>';
        const sizes=group.products.map(product=>{
            const available=Math.max(0,product.pos-(cart[product.id]||0));
            const selected=catalogSelection.productId===product.id;
            return `<button type="button" class="catalog-size min-h-10 border px-2 py-1 text-left transition ${selected?'border-jet-black bg-electric-lime':'border-surface-variant bg-white hover:border-jet-black'} disabled:cursor-not-allowed disabled:opacity-40" data-product-id="${product.id}" ${available<1?'disabled':''}><span class="block text-xs font-bold">${escapeHtml(product.size)}</span><span class="block text-[9px] text-secondary">${available} available</span></button>`;
        }).join('');
        const selectedProduct=group.products.find(item=>item.id===catalogSelection.productId);
        const selectedAvailable=selectedProduct?Math.max(0,selectedProduct.pos-(cart[selectedProduct.id]||0)):0;
        const sizePanel=expanded?`<div class="border-t border-surface-variant px-2 pb-2 pt-2"><p class="mb-1.5 text-[9px] font-semibold uppercase tracking-wider text-secondary">Select Size</p><div class="grid grid-cols-3 gap-1">${sizes}</div>${selectedProduct?`<div class="mt-2 flex items-end gap-2"><label class="min-w-0 flex-1 text-[9px] font-semibold uppercase tracking-wider text-secondary">Quantity<input class="catalog-quantity mt-1 h-9 w-full border border-surface-variant px-2 text-sm text-jet-black" type="number" min="1" max="${selectedAvailable}" value="${catalogSelection.quantity}" aria-label="Quantity for ${escapeHtml(selectedProduct.name)} ${escapeHtml(selectedProduct.size)}"></label><button class="catalog-add h-9 shrink-0 bg-jet-black px-3 text-[10px] font-semibold uppercase text-white hover:bg-neutral-700 disabled:opacity-40" type="button" data-product-id="${selectedProduct.id}" ${selectedAvailable<1?'disabled':''}>Add</button></div>`:''}</div>`:'';
        return `<article class="catalog-card min-w-0 overflow-hidden border ${expanded?'border-jet-black':'border-surface-variant'} bg-white"><button type="button" class="catalog-group block w-full p-2 text-left hover:bg-surface-container/40" data-group-id="${group.id}" aria-expanded="${expanded}"><span class="relative block aspect-square w-full overflow-hidden border border-surface-variant bg-surface-container">${image}</span><span class="mt-2 flex min-w-0 items-start justify-between gap-1"><span class="min-w-0"><span class="block truncate text-xs font-bold uppercase tracking-wide text-jet-black">${escapeHtml(group.name)}</span><span class="mt-0.5 block truncate text-[10px] text-secondary">${escapeHtml(group.variant)}</span></span><span class="shrink-0 pt-0.5 font-mono-data text-xs font-bold text-jet-black">${money(group.products[0].price)}</span></span></button>${sizePanel}</article>`;
    }).join('');
    byId('catalog-empty').classList.toggle('hidden',matches.length>0);
    list.querySelectorAll('.catalog-group').forEach(button=>button.addEventListener('click',()=>{const id=Number(button.dataset.groupId);catalogSelection.groupId=catalogSelection.groupId===id?null:id;catalogSelection.productId=null;catalogSelection.quantity=1;renderPosProductChoices();}));
    list.querySelectorAll('.catalog-size').forEach(button=>button.addEventListener('click',()=>{catalogSelection.productId=Number(button.dataset.productId);catalogSelection.quantity=1;renderPosProductChoices();list.querySelector('.catalog-quantity')?.focus();}));
    list.querySelector('.catalog-quantity')?.addEventListener('input',event=>{const input=event.currentTarget;const maximum=Number(input.max);catalogSelection.quantity=Math.min(maximum,Math.max(1,Number.parseInt(input.value,10)||1));if(input.value!==String(catalogSelection.quantity))input.value=String(catalogSelection.quantity);});
    list.querySelectorAll('.catalog-add').forEach(button=>button.addEventListener('click',()=>{const product=posProducts.find(item=>item.id===Number(button.dataset.productId));if(product&&addProduct(product,catalogSelection.quantity)){catalogSelection.productId=null;catalogSelection.quantity=1;renderPosProductChoices();}}));
}
function filterCatalog(){renderPosProductChoices();}
byId('catalog-search')?.addEventListener('input',filterCatalog);
function addProduct(product,quantity=1){const next=(cart[product.id]||0)+quantity;if(next>product.pos){message(`Only ${product.pos} unit(s) are available in POS stock for ${product.name}, ${product.size}.`);return false;}cart[product.id]=next;message('');renderCart();return true;}
function handleBarcode(){const input=byId('barcodeInput');const code=input.value.trim();if(!code)return;const product=posProducts.find(item=>item.barcode===code);if(!/^[0-9]{8}$/.test(code)){message('Enter or scan a valid 8-digit barcode.');return;}if(!product){message('Barcode not found or product is inactive.');input.select();return;}if(product.pos<1){message('This product has no available POS stock.');input.value='';input.focus();return;}addProduct(product);input.value='';input.focus();}
let barcodeScanTimer;const barcodeInput=byId('barcodeInput');barcodeInput?.addEventListener('input',()=>{const numeric=barcodeInput.value.replace(/\D/g,'').slice(0,8);if(barcodeInput.value!==numeric)barcodeInput.value=numeric;clearTimeout(barcodeScanTimer);if(/^[0-9]{8}$/.test(numeric)){barcodeScanTimer=setTimeout(()=>handleBarcode(),140);}});
byId('barcode-form')?.addEventListener('submit',event=>{event.preventDefault();clearTimeout(barcodeScanTimer);handleBarcode();});byId('barcodeInput')?.addEventListener('keydown',event=>{if(event.key==='Enter'){event.preventDefault();clearTimeout(barcodeScanTimer);handleBarcode();}});
function changeQty(id,delta){const product=posProducts.find(item=>item.id===id);if(!product)return;const next=(cart[id]||0)+delta;if(next<1){delete cart[id];message('');}else if(next<=product.pos){cart[id]=next;message('');}else{message(`Only ${product.pos} unit(s) are available in POS stock.`);}renderCart();}
function removeCartItem(id){delete cart[id];message('');renderCart();}
function applyPromo(){const eligibleEntries=Object.entries(cart).map(([id,quantity])=>({id:Number(id),quantity,product:posProducts.find(item=>item.id===Number(id))})).filter(item=>item.product?.promotion_id).sort((a,b)=>a.id-b.id);const eligibleQuantity=eligibleEntries.reduce((sum,item)=>sum+item.quantity,0);const groups=Math.floor(eligibleQuantity/2);if(!groups)return {discount:0,total:subtotal};let unitsToBundle=groups*2;const covered=eligibleEntries.map(item=>{const units=Math.min(item.quantity,unitsToBundle);unitsToBundle-=units;return {...item,units};}).filter(item=>item.units>0);const coveredRetailCents=covered.reduce((sum,item)=>sum+item.units*Math.round(item.product.price*100),0);const bundleCents=Math.min(groups*89900,coveredRetailCents);let allocatedBundleCents=0;let eligibleSubtotalCents=0;let eligibleTotalCents=0;const bundledUnits=new Map();covered.forEach((item,index)=>{const share=index===covered.length-1?bundleCents-allocatedBundleCents:Math.round(bundleCents*(item.units*Math.round(item.product.price*100))/Math.max(1,coveredRetailCents));allocatedBundleCents+=share;bundledUnits.set(item.id,item.units);eligibleTotalCents+=share;});eligibleEntries.forEach(item=>{const unitCents=Math.round(item.product.price*100);eligibleSubtotalCents+=item.quantity*unitCents;eligibleTotalCents+=(item.quantity-(bundledUnits.get(item.id)||0))*unitCents;});const discountCents=eligibleSubtotalCents-eligibleTotalCents;return {discount:discountCents/100,total:subtotal-discountCents/100};}
let subtotal=0;
let grandTotal=0;
function renderCart(){const list=byId('cartItemsList');const entries=Object.entries(cart).filter(([,qty])=>qty>0);byId('addAnotherPosProduct').classList.toggle('hidden',entries.length===0);byId('selected-products-heading').classList.toggle('hidden',entries.length===0);renderPosProductChoices();byId('cartEmpty').classList.toggle('hidden',entries.length>0);list.classList.toggle('hidden',entries.length===0);list.classList.toggle('flex',entries.length>0);list.innerHTML=entries.map(([id,qty])=>{const product=posProducts.find(item=>item.id===Number(id));return `<div class="flex items-center justify-between gap-2 border-b border-surface-variant py-space-xs"><span class="flex h-11 w-11 shrink-0 items-center justify-center overflow-hidden bg-surface-container">${product.photo?`<img class=\"h-full w-full object-contain\" src=\"${escapeHtml(product.photo)}\" alt=\"\">`:'<span class=\"material-symbols-outlined text-secondary\">image</span>'}</span><div class="min-w-0 flex-1"><p class="truncate text-xs font-semibold">${escapeHtml(product.name)}</p><p class="truncate text-[10px] text-secondary">${escapeHtml(product.variant)} · ${escapeHtml(product.size)} · Barcode: ${product.barcode} · ${Math.max(0,product.pos-qty)} POS units left to add</p><p class="text-xs text-secondary">${money(product.price)} each</p></div><div class="flex items-center gap-1"><button type="button" class="qty-change flex h-7 w-7 items-center justify-center rounded bg-white text-secondary" data-id="${id}" data-delta="-1" aria-label="Decrease quantity">−</button><span class="w-5 text-center text-xs font-bold">${qty}</span><button type="button" class="qty-change flex h-7 w-7 items-center justify-center rounded bg-white text-secondary disabled:cursor-not-allowed disabled:opacity-40" data-id="${id}" data-delta="1" aria-label="Increase quantity" ${qty>=product.pos?'disabled':''}>+</button><button type="button" class="cart-remove flex h-7 w-7 items-center justify-center rounded text-secondary hover:bg-white hover:text-error" data-id="${id}" aria-label="Remove item" title="Remove item"><span class="material-symbols-outlined text-base">delete</span></button></div><span class="w-20 text-right text-xs font-bold">${money(product.price*qty)}</span></div>`;}).join('');list.querySelectorAll('.qty-change').forEach(button=>button.addEventListener('click',()=>changeQty(Number(button.dataset.id),Number(button.dataset.delta))));list.querySelectorAll('.cart-remove').forEach(button=>button.addEventListener('click',()=>removeCartItem(Number(button.dataset.id))));subtotal=entries.reduce((sum,[id,qty])=>sum+posProducts.find(item=>item.id===Number(id)).price*qty,0);const promo=applyPromo();byId('subtotalVal').textContent=money(subtotal);byId('discountVal').textContent='-'+money(promo.discount);grandTotal=promo.total;byId('grandTotalVal').textContent=money(grandTotal);byId('cartItemCount').textContent=entries.reduce((sum,[,qty])=>sum+qty,0);const callout=byId('promo-callout');const promoApplied=promo.discount>0;callout.classList.toggle('hidden',!promoApplied);callout.classList.toggle('flex',promoApplied);if(promoApplied){byId('promo-callout-discount').textContent='-'+money(promo.discount);}byId('checkout-items').innerHTML=entries.map(([id,qty])=>`<input type="hidden" name="items[${id}]" value="${qty}">`).join('');}
byId('resetCart')?.addEventListener('click',clearCart);byId('clearCart')?.addEventListener('click',clearCart);function clearCart(){cart={};message('');renderCart();}
renderCart();
byId('addAnotherPosProduct')?.addEventListener('click',()=>byId('openCatalogButton').click());
let checkoutPending=false;
function submitPosSale(){if(checkoutPending)return;checkoutPending=true;byId('payCash').disabled=true;byId('payQr').disabled=true;byId('checkout-form-submit').requestSubmit();}
function cashQuickValues(total){const values=new Set([Number(total.toFixed(2))]);[50,100,500].forEach(step=>values.add(Math.ceil((total+0.001)/step)*step));return [...values].sort((first,second)=>first-second).slice(0,4);}
function updateCashChange(){const raw=byId('cashReceived').value.trim().replace(/,/g,'');const received=raw&&/^\d*\.?\d{0,2}$/.test(raw)?Number(raw):0;const difference=Math.round((received-grandTotal)*100)/100;byId('cashChange').textContent=money(Math.max(0,difference));byId('cashChangeHint').textContent=difference<0?`Short by ${money(Math.abs(difference))}. Enter enough cash to complete.`:difference===0?'Exact amount received. No change due.':'Change to return to customer.';byId('completeCashSale').disabled=received<grandTotal||!Object.keys(cart).length;}
function openCashCheckout(){if(!Object.keys(cart).length){message('Cart is empty. Scan or select an item first.');return;}byId('payment-method').value='cash';byId('qr-confirmed').value='';byId('cashTotal').textContent=money(grandTotal);byId('cashReceived').value=grandTotal.toFixed(2);byId('cashQuickAmounts').innerHTML=cashQuickValues(grandTotal).map(amount=>`<button type="button" class="min-h-10 border border-surface-variant bg-white px-1 text-xs font-semibold transition hover:border-jet-black hover:bg-electric-lime" data-cash-amount="${amount.toFixed(2)}">${money(amount)}</button>`).join('');byId('cashQuickAmounts').querySelectorAll('[data-cash-amount]').forEach(button=>button.addEventListener('click',()=>{byId('cashReceived').value=button.dataset.cashAmount;updateCashChange();}));updateCashChange();showModal('cashModal');byId('cashReceived').focus();byId('cashReceived').select();}
byId('cashReceived')?.addEventListener('input',updateCashChange);
byId('cashReceived')?.addEventListener('keydown',event=>{if(event.key==='Enter'&&!byId('completeCashSale').disabled){event.preventDefault();byId('completeCashSale').click();}});
byId('completeCashSale')?.addEventListener('click',()=>{updateCashChange();if(byId('completeCashSale').disabled)return;hideModal('cashModal');submitPosSale();});
byId('payCash')?.addEventListener('click',openCashCheckout);
byId('payQr')?.addEventListener('click',()=>{if(!Object.keys(cart).length){message('Cart is empty. Scan or select an item first.');return;}byId('qrTotal').textContent=money(grandTotal);showModal('qrModal');});
byId('confirmQrPayment')?.addEventListener('click',()=>{if(!byId('qrPaymentConfirmed').checked)return;byId('payment-method').value='qr';byId('qr-confirmed').value='1';submitPosSale();});
if(byId('pos-withdrawal-form')){
    window.withdrawalItems=window.withdrawalItems||{};
    const withdrawalItems=window.withdrawalItems;
    let withdrawalScanTimer=null;
    const withdrawalError=text=>{byId('pos-withdrawal-error').textContent=text;byId('pos-withdrawal-error').classList.toggle('hidden',!text);};
    window.posWithdrawalError=withdrawalError;
    function renderWithdrawalItems(){const ids=Object.keys(withdrawalItems);byId('pos-withdrawal-list-empty').classList.toggle('hidden',ids.length>0);byId('pos-withdrawal-add-another').classList.toggle('hidden',ids.length===0);byId('pos-withdrawal-count').textContent=`${ids.length} item${ids.length===1?'':'s'}`;byId('pos-withdrawal-submit').disabled=ids.length===0;byId('pos-withdrawal-list').innerHTML=ids.map(id=>{const product=posProducts.find(item=>item.id===Number(id));const quantity=withdrawalItems[id];return `<div class="flex min-w-0 items-center gap-2 py-2"><div class="min-w-0 flex-1"><p class="truncate text-xs font-semibold">${escapeHtml(product.name)} · ${escapeHtml(product.variant)} · ${escapeHtml(product.size)}</p><p class="truncate font-mono text-[9px] text-secondary">Barcode: ${escapeHtml(product.barcode)} · ${product.pos} POS stock</p></div><div class="flex shrink-0 items-center gap-1"><button type="button" class="withdrawal-qty h-8 w-8 rounded bg-surface-container-low disabled:opacity-40" data-id="${id}" data-delta="-1" aria-label="Decrease withdrawal quantity">−</button><span class="w-5 text-center text-xs font-bold">${quantity}</span><button type="button" class="withdrawal-qty h-8 w-8 rounded bg-surface-container-low disabled:opacity-40" data-id="${id}" data-delta="1" aria-label="Increase withdrawal quantity" ${quantity>=product.pos?'disabled':''}>+</button><button type="button" class="withdrawal-remove ml-1 h-8 w-8 rounded text-error hover:bg-error/10" data-id="${id}" aria-label="Remove withdrawal item">×</button></div></div>`;}).join('');byId('pos-withdrawal-items').innerHTML=ids.map(id=>`<input type="hidden" name="items[${id}]" value="${withdrawalItems[id]}">`).join('');byId('pos-withdrawal-list').querySelectorAll('.withdrawal-qty').forEach(button=>button.addEventListener('click',()=>{const id=Number(button.dataset.id);const next=withdrawalItems[id]+Number(button.dataset.delta);if(next<1)delete withdrawalItems[id];else if(next<=posProducts.find(item=>item.id===id).pos)withdrawalItems[id]=next;renderWithdrawalItems();}));byId('pos-withdrawal-list').querySelectorAll('.withdrawal-remove').forEach(button=>button.addEventListener('click',()=>{delete withdrawalItems[Number(button.dataset.id)];renderWithdrawalItems();}));}
    function addWithdrawalItem(id,quantity=1){const product=posProducts.find(item=>item.id===Number(id));if(!product||product.pos<1){withdrawalError('This product has no available POS stock.');return false;}const next=(withdrawalItems[id]||0)+Number(quantity);if(!Number.isInteger(Number(quantity))||Number(quantity)<1||next>product.pos){withdrawalError(`Only ${Math.max(0,product.pos-(withdrawalItems[id]||0))} more unit(s) are available in POS stock for ${product.name}, ${product.size}.`);return false;}withdrawalItems[id]=next;withdrawalError('');renderWithdrawalItems();return true;}
    window.addPosWithdrawalItem=addWithdrawalItem;
    function scanWithdrawalBarcode(){const input=byId('pos-withdrawal-barcode');const code=input.value.trim();if(!code)return;if(!/^[0-9]{8}$/.test(code)){withdrawalError('Scan or enter a valid 8-digit barcode.');return;}const product=posProducts.find(item=>item.barcode===code);if(!product){withdrawalError('Barcode not found or product is inactive.');input.select();return;}input.value='';addWithdrawalItem(product.id,1);input.focus();}
    const withdrawalSelect=byId('pos-withdrawal-product-select');
    withdrawalSelect.addEventListener('change',()=>{const id=Number(withdrawalSelect.value);if(!id)return;addWithdrawalItem(id,1);withdrawalSelect.value='';});
    byId('pos-withdrawal-add-another').addEventListener('click',()=>withdrawalSelect.focus());
    byId('pos-withdrawal-scan').addEventListener('click',scanWithdrawalBarcode);
    const withdrawalBarcode=byId('pos-withdrawal-barcode');
    withdrawalBarcode.addEventListener('input',()=>{const numeric=withdrawalBarcode.value.replace(/\D/g,'').slice(0,8);if(withdrawalBarcode.value!==numeric)withdrawalBarcode.value=numeric;clearTimeout(withdrawalScanTimer);if(/^[0-9]{8}$/.test(numeric))withdrawalScanTimer=setTimeout(scanWithdrawalBarcode,140);});
    withdrawalBarcode.addEventListener('keydown',event=>{if(event.key==='Enter'){event.preventDefault();clearTimeout(withdrawalScanTimer);scanWithdrawalBarcode();}});
    byId('pos-withdrawal-reason').addEventListener('change',event=>{const others=event.target.value==='Others';byId('pos-withdrawal-notes-wrap').classList.toggle('hidden',!others);byId('pos-withdrawal-notes-wrap').style.display=others?'block':'none';byId('pos-withdrawal-notes').required=others;if(!others)byId('pos-withdrawal-notes').value='';});
    byId('pos-withdrawal-form').addEventListener('submit',event=>{if(!Object.keys(withdrawalItems).length){event.preventDefault();withdrawalError('Select at least one product size to withdraw.');return;}if(Object.entries(withdrawalItems).some(([id,quantity])=>quantity>posProducts.find(item=>item.id===Number(id)).pos)){event.preventDefault();withdrawalError('A withdrawal quantity cannot exceed available POS stock.');}});
    document.querySelector('[data-modal-open="pos-withdrawal-modal"]')?.addEventListener('click',()=>{withdrawalError('');renderWithdrawalItems();});
}
const exchangeSelection={returned:null,replacement:null};
function exchangeMessage(text=''){const element=byId('exchange-message');element.textContent=text;element.classList.toggle('hidden',!text);}
function clearExchangeProduct(target){
    exchangeSelection[target]=null;
    byId(`exchange-${target}-id`).value='';
    byId(`exchange-${target}-select`).value='';
    const quantity=byId(target==='returned'?'exchange-return-qty':'exchange-replacement-qty');
    quantity.value='1';quantity.disabled=true;quantity.removeAttribute('max');
    byId(`exchange-${target}-summary`).textContent='No product selected.';
    byId(`exchange-${target}-stock`).textContent=target==='returned'?'Select a returned product to see available POS stock.':'Select a replacement to see available POS stock.';
    updateExchange();
}
function updateExchange(){
    const returned=exchangeSelection.returned;
    const replacement=exchangeSelection.replacement;
    const returnedQty=Number(byId('exchange-return-qty').value||0);
    const replacementQty=Number(byId('exchange-replacement-qty').value||0);
    const difference=returned&&replacement?(replacement.price*replacementQty)-(returned.price*returnedQty):0;
    byId('exchange-difference').textContent=money(Math.max(0,difference));
    byId('exchange-payment').classList.toggle('hidden',difference<=0);
    byId('completeExchange').disabled=!(returned&&replacement&&returnedQty>=1&&replacementQty>=1&&replacementQty<=replacement.pos);
}
function setExchangeProduct(target,product){
    if(target==='replacement'&&product.pos<1){exchangeMessage('This product has no available POS stock for replacement.');return false;}
    exchangeSelection[target]=product;
    byId(`exchange-${target}-select`).value=String(product.id);
    byId(`exchange-${target}-id`).value=product.id;
    const quantity=byId(target==='returned'?'exchange-return-qty':'exchange-replacement-qty');
    quantity.value='1';quantity.disabled=false;
    if(target==='replacement')quantity.max=String(product.pos);else quantity.removeAttribute('max');
    byId(`exchange-${target}-summary`).textContent=`${product.name} · ${product.variant} · ${product.size} · Barcode: ${product.barcode}`;
    byId(`exchange-${target}-stock`).textContent=`${target==='returned'?'Current':'Available'} POS Stock: ${product.pos} unit(s).`;
    exchangeMessage('');updateExchange();return true;
}
function processExchangeBarcode(target){const input=byId(`exchange-${target}-barcode`);const code=input.value.trim();if(!code)return;if(!/^[0-9]{8}$/.test(code)){exchangeMessage('Scan or enter a valid 8-digit barcode.');return;}const product=posProducts.find(item=>item.barcode===code);if(!product){exchangeMessage('Barcode not found or product is inactive.');input.select();return;}if(setExchangeProduct(target,product)){input.value='';input.focus();}}
const exchangeScanTimers={returned:null,replacement:null};
['returned','replacement'].forEach(target=>{
    const input=byId(`exchange-${target}-barcode`);
    const quantity=byId(target==='returned'?'exchange-return-qty':'exchange-replacement-qty');
    const select=byId(`exchange-${target}-select`);
    select.addEventListener('change',()=>{const id=Number(select.value);if(!id){clearExchangeProduct(target);return;}const product=posProducts.find(item=>item.id===id);if(product)setExchangeProduct(target,product);});
    input.addEventListener('input',()=>{const numeric=input.value.replace(/\D/g,'').slice(0,8);if(input.value!==numeric)input.value=numeric;clearTimeout(exchangeScanTimers[target]);if(/^[0-9]{8}$/.test(numeric))exchangeScanTimers[target]=setTimeout(()=>processExchangeBarcode(target),140);});
    input.addEventListener('keydown',event=>{if(event.key==='Enter'){event.preventDefault();clearTimeout(exchangeScanTimers[target]);processExchangeBarcode(target);}});
    quantity.addEventListener('input',updateExchange);quantity.addEventListener('change',updateExchange);
});
document.querySelectorAll('.exchange-scan').forEach(button=>button.addEventListener('click',()=>processExchangeBarcode(button.dataset.target)));
byId('exchange-form').addEventListener('submit',event=>{updateExchange();if(!exchangeSelection.returned||!exchangeSelection.replacement){event.preventDefault();exchangeMessage('Choose both the returned product and replacement product.');return;}if(Number(byId('exchange-replacement-qty').value)>exchangeSelection.replacement.pos){event.preventDefault();exchangeMessage(`Only ${exchangeSelection.replacement.pos} unit(s) are available for the replacement.`);}});
updateExchange();
</script>
<style>.pos-panel-button{display:flex;min-width:0;min-height:2.5rem;align-items:center;justify-content:center;gap:.3rem;white-space:nowrap;border-radius:.375rem;padding:.25rem .3rem;color:#5f5e5e;font-size:.7rem;font-weight:600;transition:background .15s,color .15s}.pos-panel-button:hover{background:#eceef0;color:#191c1e}.pos-panel-button .material-symbols-outlined{flex-shrink:0;font-size:18px}</style>
@endsection
