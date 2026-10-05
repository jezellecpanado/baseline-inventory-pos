@extends('layouts.app')

@section('title', $adminWorkspace ? 'Admin' : 'Dashboard')

@section('content')
@if ($adminWorkspace)
@include('admin-panel')
@else
<div class="mx-auto w-full max-w-[1600px] px-gutter py-space-xl sm:px-gutter-tablet lg:px-gutter-desktop">
    <div class="mb-space-xl flex flex-col justify-between gap-space-md border-b border-surface-variant pb-space-lg md:flex-row md:items-end">
        <div>
            <div class="mb-space-xs flex items-center gap-space-xs font-mono-data text-label-sm uppercase tracking-wider text-secondary"><span class="inline-block h-2 w-2 bg-primary-container"></span>Overview</div>
            <h1 class="font-headline-lg text-headline-lg font-bold uppercase tracking-tight">Sales &amp; Stock Performance</h1>
        </div>
        <div class="flex max-w-full overflow-x-auto border border-on-surface bg-surface-container-lowest" aria-label="Select date range">
            @foreach (['today'=>'Today','week'=>'This Week','month'=>'This Month'] as $value => $label)
                <a class="whitespace-nowrap border-b-2 px-space-md py-space-xs font-label-sm text-label-sm uppercase tracking-wider transition-colors {{ $currentRange === $value ? 'border-primary-container bg-on-surface text-surface-container-lowest' : 'border-transparent text-secondary hover:text-on-surface' }}" href="{{ route('dashboard', ['range'=>$value]) }}">{{ $label }}</a>
            @endforeach
            <button class="flex items-center gap-1 whitespace-nowrap border-l border-surface-variant px-space-md py-space-xs font-label-sm text-label-sm uppercase tracking-wider text-secondary hover:text-on-surface" type="button" onclick="document.getElementById('custom-range').classList.toggle('hidden')">Custom <span class="material-symbols-outlined text-[14px]">calendar_today</span></button>
        </div>
    </div>
    <form id="custom-range" class="{{ $currentRange === 'custom' ? '' : 'hidden' }} mb-space-lg flex flex-wrap items-end gap-space-md border border-surface-variant bg-surface-container-lowest p-space-md" method="GET" action="{{ route('dashboard') }}">
        <input type="hidden" name="range" value="custom">
        <label class="font-label-sm text-label-sm uppercase text-secondary">From<input class="form-input" type="date" name="from" value="{{ request('from', $from) }}" required></label>
        <label class="font-label-sm text-label-sm uppercase text-secondary">To<input class="form-input" type="date" name="to" value="{{ request('to', $to) }}" required></label>
        <button class="bg-inverse-surface px-4 py-2 text-sm font-semibold text-white" type="submit">Apply</button>
    </form>

    @if ($user->isAdmin())
    <div class="mb-space-2xl grid grid-cols-1 gap-space-md sm:grid-cols-2 lg:grid-cols-4">
        <article class="flex flex-col justify-between border border-surface-variant bg-surface-container-lowest p-space-lg"><div class="mb-space-md flex items-center justify-between"><span class="font-label-sm text-label-sm uppercase tracking-wider text-secondary">Total Sales</span><span class="material-symbols-outlined text-[18px] text-secondary">payments</span></div><div class="flex items-baseline gap-space-sm"><span class="font-display-md text-display-md tracking-tight">₱{{ number_format($salesTotal,2) }}</span></div><div class="mt-space-sm flex items-center justify-between border-t border-surface-variant pt-space-sm text-secondary"><span class="font-body-sm text-body-sm">Gross retail volume</span><span class="font-mono-data text-label-sm font-semibold text-on-surface">POS ₱{{ number_format($posSales,2) }} · WH ₱{{ number_format($warehouseSales,2) }}</span></div></article>
        <article class="flex flex-col justify-between border border-surface-variant bg-surface-container-lowest p-space-lg"><div class="mb-space-md flex items-center justify-between"><span class="font-label-sm text-label-sm uppercase tracking-wider text-secondary">Units Sold</span><span class="material-symbols-outlined text-[18px] text-secondary">shopping_bag</span></div><div class="flex items-baseline gap-space-sm"><span class="font-display-md text-display-md tracking-tight">{{ number_format($unitsSold) }}</span></div><div class="mt-space-sm flex items-center justify-between border-t border-surface-variant pt-space-sm text-secondary"><span class="font-body-sm text-body-sm">Across all channels</span><span class="font-mono-data text-label-sm font-semibold text-on-surface">{{ number_format($salesTransactions) }} transactions</span></div></article>
        <article class="flex flex-col justify-between border border-surface-variant bg-surface-container-lowest p-space-lg"><div class="mb-space-md flex items-center justify-between"><span class="font-label-sm text-label-sm uppercase tracking-wider text-secondary">Warehouse Stock Units</span><span class="material-symbols-outlined text-[18px] text-secondary">warehouse</span></div><div class="flex items-baseline gap-space-sm"><span class="font-display-md text-display-md tracking-tight">{{ number_format($warehouseTotal) }}</span></div><div class="mt-space-sm border-t border-surface-variant pt-space-sm font-body-sm text-secondary">Current Warehouse inventory</div></article>
        <article class="flex flex-col justify-between border border-surface-variant bg-surface-container-lowest p-space-lg"><div class="mb-space-md flex items-center justify-between"><span class="font-label-sm text-label-sm uppercase tracking-wider text-secondary">POS Stock Units</span><span class="material-symbols-outlined text-[18px] text-secondary">point_of_sale</span></div><div class="flex items-baseline gap-space-sm"><span class="font-display-md text-display-md text-on-surface tracking-tight">{{ number_format($posTotal) }}</span></div><div class="mt-space-sm border-t border-surface-variant pt-space-sm font-body-sm text-secondary">Current POS inventory</div></article>
    </div>
    @else
    <div class="mb-space-2xl grid grid-cols-1 gap-space-md sm:grid-cols-2 lg:grid-cols-4">
        @if ($user->role === 'warehouse_staff')
        <article class="flex min-h-40 flex-col justify-between border border-surface-variant bg-surface-container-lowest p-space-lg"><div class="flex items-center justify-between"><span class="font-label-sm text-label-sm uppercase tracking-wider text-secondary">Warehouse Stock Units</span><span class="material-symbols-outlined text-[18px] text-secondary">warehouse</span></div><div class="font-display-md text-display-md tracking-tight">{{ number_format($warehouseTotal) }}</div><div class="border-t border-surface-variant pt-space-sm font-body-sm text-secondary">Current Warehouse inventory</div></article>
        @else
        <article class="flex min-h-40 flex-col justify-between border border-surface-variant bg-surface-container-lowest p-space-lg"><div class="flex items-center justify-between"><span class="font-label-sm text-label-sm uppercase tracking-wider text-secondary">POS Stock Units</span><span class="material-symbols-outlined text-[18px] text-secondary">point_of_sale</span></div><div class="font-display-md text-display-md tracking-tight">{{ number_format($posTotal) }}</div><div class="border-t border-surface-variant pt-space-sm font-body-sm text-secondary">Current POS inventory</div></article>
        @endif
    </div>
    @endif

    <div class="grid grid-cols-1 items-start gap-space-xl lg:grid-cols-12">
        @if ($user->isAdmin())
        <section class="flex flex-col lg:col-span-8">
            <div class="mb-space-md flex items-center justify-between border-b border-on-surface pb-space-sm"><div class="flex items-center gap-space-sm"><span class="h-3 w-1.5 bg-primary-container"></span><h2 class="font-headline-sm text-headline-sm uppercase tracking-tight text-on-surface">Product Sales Performance</h2></div><span class="font-mono-data text-label-sm uppercase text-secondary">Showing {{ count($performance) }} Top Performers</span></div>
            <div class="w-full overflow-x-auto border border-surface-variant bg-surface-container-lowest"><table class="w-full min-w-[760px] border-collapse text-left">
                <thead>
                    <tr class="border-b border-on-surface bg-surface-container-low text-left font-label-sm text-label-sm uppercase tracking-wider text-secondary">
                        <th class="px-space-md py-space-sm" scope="col">Product</th>
                        <th class="px-space-md py-space-sm text-right" scope="col"><a href="{{ request()->fullUrlWithQuery(['sort'=>'units']) }}">Units Sold{{ $currentSort==='units' ? ' ↓' : '' }}</a></th>
                        <th class="px-space-md py-space-sm text-right" scope="col"><a href="{{ request()->fullUrlWithQuery(['sort'=>'sales']) }}">Sales Amount{{ $currentSort==='sales' ? ' ↓' : '' }}</a></th>
                        <th class="px-space-md py-space-sm text-right" scope="col">Warehouse</th>
                        <th class="px-space-md py-space-sm text-right" scope="col">POS</th>
                        <th class="px-space-md py-space-sm text-right text-on-surface" scope="col">Total Remaining</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-surface-variant font-mono-data text-body-sm">
                @forelse ($performance as $row)
                    <tr class="transition-colors hover:bg-surface-container-low"><td class="px-space-md py-space-sm"><div class="flex items-center gap-space-md">@if ($row['photo'])<img class="h-10 w-10 shrink-0 border border-surface-variant object-cover" src="{{ asset('storage/'.$row['photo']) }}" alt="{{ $row['name'] }}">@else<span class="flex h-10 w-10 shrink-0 items-center justify-center border border-surface-variant bg-surface-container-low text-secondary"><span class="material-symbols-outlined text-[18px]">image</span></span>@endif<div class="min-w-0"><div class="truncate font-label-lg text-label-lg text-on-surface">{{ $row['name'] }}</div><div class="font-mono-data text-body-sm text-secondary">{{ $row['variant'] }} · Size {{ $row['size'] }}</div><div class="font-mono-data text-xs text-secondary">Barcode: {{ $row['barcode'] }}</div></div></div></td><td class="px-space-md py-space-sm text-right font-semibold">{{ number_format($row['units']) }}</td><td class="px-space-md py-space-sm text-right font-semibold">₱{{ number_format($row['sales'],2) }}</td><td class="px-space-md py-space-sm text-right text-secondary">{{ number_format($row['warehouse']) }}</td><td class="px-space-md py-space-sm text-right text-secondary">{{ number_format($row['pos']) }}</td><td class="px-space-md py-space-sm text-right font-bold">{{ number_format($row['warehouse'] + $row['pos']) }}</td></tr>
                @empty
                    <tr><td class="px-4 py-12 text-center text-secondary" colspan="6">No product sales in this date range.</td></tr>
                @endforelse
                </tbody>
            </table></div>
        </section>
        @endif
        <section class="flex flex-col {{ $user->isAdmin() ? 'lg:col-span-4' : 'lg:col-span-12' }}">
            <div class="mb-space-md flex items-center justify-between border-b border-on-surface pb-space-sm"><div class="flex items-center gap-space-sm"><span class="h-3 w-1.5 bg-jet-black"></span><h2 class="font-headline-sm text-headline-sm font-semibold uppercase tracking-tight">Recent Activity</h2></div><span class="font-mono-data text-label-sm uppercase text-secondary">Live Feed</span></div>
            <div class="divide-y divide-surface-variant border border-surface-variant bg-surface-container-lowest">
            @forelse ($recent as $activity)
                <article class="flex items-start gap-space-md p-space-md transition-colors hover:bg-surface-container-low"><span class="flex h-7 w-7 shrink-0 items-center justify-center bg-primary-container font-label-sm text-label-sm font-bold text-on-surface">{{ strtoupper(substr($activity->movement_type,0,2)) }}</span><div class="min-w-0 flex-1"><div class="mb-0.5 flex items-center justify-between gap-2"><span class="truncate font-label-sm text-label-sm uppercase tracking-wider text-on-surface">{{ $activity->movement_type }}</span><time class="shrink-0 font-mono-data text-body-sm text-secondary">{{ $activity->created_at->timezone('Asia/Manila')->format('H:i:s') }}</time></div><div class="truncate font-body-sm text-secondary">Ref: <span class="font-mono-data text-on-surface">{{ $activity->reference }}</span> · {{ $activity->product_name }} · {{ abs($activity->quantity_change) }} units</div><div class="mt-space-xs flex items-center justify-between gap-2 font-mono-data text-body-sm"><span class="truncate text-secondary">{{ $activity->location }} · {{ $activity->user_name }}</span><span class="shrink-0 font-semibold {{ $activity->quantity_change > 0 ? 'text-on-surface' : ($activity->quantity_change < 0 ? 'text-error' : 'text-secondary') }}">{{ $activity->quantity_change > 0 ? '+' : '' }}{{ $activity->quantity_change }} units</span></div></div></article>
            @empty
                <div class="py-12 text-center font-body-sm text-secondary">No recent activity.</div>
            @endforelse
            </div>
        </section>
    </div>
</div>

@endif
<script>
document.addEventListener('DOMContentLoaded',()=>{const search=document.getElementById('product-search');const category=document.getElementById('product-category-filter');const filter=()=>{document.querySelectorAll('.product-row').forEach(row=>{const q=(search?.value||'').toLowerCase();row.classList.toggle('hidden',!row.innerText.toLowerCase().includes(q)||(category?.value&&row.dataset.category!==category.value));});};search?.addEventListener('input',filter);category?.addEventListener('change',filter);const productForm=document.getElementById('product-form');if(productForm&&window.location.hash==='#product-form')productForm.open=true;const barcode=document.getElementById('product-barcode');document.getElementById('scan-barcode')?.addEventListener('click',()=>{barcode?.focus();barcode?.select();});barcode?.addEventListener('keydown',event=>{if(event.key==='Enter'){event.preventDefault();barcode.closest('label')?.nextElementSibling?.querySelector('input')?.focus();}});});
function exportTable(tableId,fileName){const table=document.getElementById(tableId);if(!table)return;const rows=[...table.querySelectorAll('tr')].filter(row=>!row.classList.contains('hidden'));const csv=rows.map(row=>[...row.querySelectorAll('th,td')].map(cell=>'"'+cell.innerText.replaceAll('"','""').trim()+'"').join(',')).join('\r\n');const link=document.createElement('a');link.href=URL.createObjectURL(new Blob([csv],{type:'text/csv'}));link.download=fileName;link.click();URL.revokeObjectURL(link.href);}
</script>
@endsection
