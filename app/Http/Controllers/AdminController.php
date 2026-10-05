<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Models\InventoryBalance;
use App\Models\InventoryTransaction;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\User;
use App\Services\InventoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AdminController extends Controller
{
    public function store(Request $request, string $action): RedirectResponse
    {
        return match ($action) {
            'product' => $this->saveProduct($request),
            'product-delete' => $this->deleteProduct($request),
            'product-status' => $this->setProductStatus($request),
            'product-promotion' => $this->setProductPromotion($request),
            'user' => $this->saveUser($request),
            'user-delete' => $this->deleteUser($request),
            'qr' => $this->saveQr($request),
            default => abort(404),
        };
    }

    public function void(Request $request, InventoryTransaction $transaction, InventoryService $inventory): RedirectResponse
    {
        $data = $request->validate(['void_reason' => ['required', 'string', 'max:2000']]);
        $inventory->void($request->user(), $transaction, $data['void_reason']);

        return redirect()->route('admin.index', ['tab' => 'voids'])->with('success', $transaction->reference.' was voided and reversed.');
    }

    private function saveProduct(Request $request): RedirectResponse
    {
        $id = $request->integer('id') ?: null;
        $product = $id ? Product::query()->findOrFail($id) : null;
        $rules = [
            'design_name' => ['required', 'string', 'max:255'],
            'category' => ['required', Rule::in(['Court Series', 'Premier Series', 'Evolution Series', 'Accessories'])],
            'variant' => ['required', Rule::in(['Unisex Dri-FIT Top', 'Women’s Dri-FIT Top', 'Women’s Dri-FIT Tank Top', 'Accessories'])],
            'standard_price' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'active' => ['sometimes', 'boolean'],
            'eligible' => ['sometimes', 'boolean'],
        ];
        if ($id) {
            $rules['size'] = ['required', Rule::in(['XS', 'S', 'M', 'L', 'XL', '2XL', '3XL'])];
            $rules['barcode'] = ['required', 'regex:/^[0-9]{8}$/', Rule::unique('products', 'barcode')->ignore($id)];
        } else {
            $rules['sizes'] = ['required', 'array', 'min:1'];
            $rules['sizes.*'] = ['required', 'distinct', Rule::in(['XS', 'S', 'M', 'L', 'XL', '2XL', '3XL'])];
            $rules['barcodes'] = ['required', 'array'];
            $rules['barcodes.*'] = ['required', 'regex:/^[0-9]{8}$/', 'distinct', Rule::unique('products', 'barcode')];
        }
        $data = $request->validate($rules);

        if ($id) {
            $siblings = Product::query()->where('design_name', $product->design_name)->where('variant', $product->variant)->get();
            $groupIds = $siblings->pluck('id');
            $targetSizes = $siblings->map(fn (Product $sibling) => $sibling->id === $product->id ? $data['size'] : $sibling->size);
            $conflict = Product::query()->where('design_name', $data['design_name'])->where('variant', $data['variant'])
                ->whereIn('size', $targetSizes)->whereNotIn('id', $groupIds)->exists();
            if ($conflict || $siblings->contains(fn (Product $sibling) => $sibling->id !== $product->id && $sibling->size === $data['size'])) {
                throw ValidationException::withMessages(['size' => 'This design, variant, and size already exists.']);
            }
            $photoPath = $request->file('photo')?->store('products', 'public');
            $sharedPhotoPath = $photoPath ?? $siblings->first(fn (Product $sibling) => filled($sibling->photo_path))?->photo_path;
            DB::transaction(function () use ($siblings, $product, $data, $request, $sharedPhotoPath): void {
                foreach ($siblings as $sibling) {
                    $sibling->fill([
                        'design_name' => $data['design_name'], 'category' => $data['category'], 'variant' => $data['variant'],
                        'standard_price' => $data['standard_price'], 'photo_path' => $sharedPhotoPath,
                    ]);
                    $sibling->save();
                }
                $product->refresh();
                $product->size = $data['size'];
                $product->barcode = $data['barcode'];
                $product->active = $request->boolean('active');
                $product->save();
            });
            InventoryBalance::query()->firstOrCreate(['product_id' => $product->id]);

            return redirect()->route('admin.index', ['tab' => 'products'])->with('success', 'Product and shared variant details saved.');
        }

        if (count($data['sizes']) !== count($data['barcodes']) || collect($data['sizes'])->contains(fn (string $size): bool => ! isset($data['barcodes'][$size]))) {
            throw ValidationException::withMessages(['sizes' => 'Enter one 8-digit barcode for every selected size.']);
        }
        $existingSizes = Product::query()->where('design_name', $data['design_name'])->where('variant', $data['variant'])
            ->whereIn('size', $data['sizes'])->pluck('size')->all();
        if ($existingSizes) {
            throw ValidationException::withMessages(['sizes' => 'These sizes are already encoded for this product and variant: '.implode(', ', $existingSizes).'.']);
        }
        $sizeOrder = ['XS', 'S', 'M', 'L', 'XL', '2XL', '3XL'];
        usort($data['sizes'], fn (string $left, string $right): int => array_search($left, $sizeOrder, true) <=> array_search($right, $sizeOrder, true));
        $photoPath = $request->file('photo')?->store('products', 'public');

        $promotionId = null;
        if ($request->boolean('eligible')) {
            $eventPromotion = Promotion::query()->where('required_quantity', 2)->where('bundle_price', 899)->orderBy('id')->first();
            if (! $eventPromotion) {
                $eventPromotion = Promotion::query()->create(['name' => 'Buy 2 for ₱899', 'required_quantity' => 2, 'bundle_price' => 899, 'active' => true]);
            } elseif (! $eventPromotion->active) {
                $eventPromotion->active = true;
                $eventPromotion->save();
            }
            $promotionId = $eventPromotion->id;
        }

        DB::transaction(function () use ($data, $photoPath, $request, $promotionId): void {
            foreach ($data['sizes'] as $size) {
                $newProduct = Product::query()->create([
                    'design_name' => $data['design_name'], 'category' => $data['category'], 'variant' => $data['variant'],
                    'size' => $size, 'barcode' => $data['barcodes'][$size], 'standard_price' => $data['standard_price'],
                    'photo_path' => $photoPath, 'active' => $request->boolean('active'), 'promotion_id' => $promotionId,
                ]);
                InventoryBalance::query()->firstOrCreate(['product_id' => $newProduct->id]);
            }
        });

        return redirect()->route('admin.index', ['tab' => 'products'])->with('success', 'Product and selected sizes saved.');
    }

    private function setProductStatus(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'active' => ['required', 'boolean'],
        ]);
        $product = Product::query()->findOrFail($data['product_id']);
        $product->active = (bool) $data['active'];
        $product->save();

        return redirect()->route('admin.index', ['tab' => 'products'])->with('success', 'Product status updated.');
    }

    private function deleteProduct(Request $request): RedirectResponse
    {
        $product = Product::query()->findOrFail($request->integer('id'));
        InventoryBalance::query()->where('product_id', $product->id)->delete();
        $product->delete();

        return redirect()->route('admin.index', ['tab' => 'products'])->with('success', 'Product deleted.');
    }

    private function setProductPromotion(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'eligible' => ['required', 'boolean'],
        ]);
        $product = Product::query()->findOrFail($data['product_id']);
        $eventPromotion = Promotion::query()
            ->where('required_quantity', 2)
            ->where('bundle_price', 899)
            ->orderBy('id')
            ->first();
        if ($request->boolean('eligible') && ! $eventPromotion) {
            $eventPromotion = Promotion::query()->create([
                'name' => 'Buy 2 for ₱899',
                'required_quantity' => 2,
                'bundle_price' => 899,
                'active' => true,
            ]);
        }
        if ($request->boolean('eligible') && ! $eventPromotion->active) {
            $eventPromotion->active = true;
            $eventPromotion->save();
        }
        $product->promotion_id = $request->boolean('eligible') ? $eventPromotion->id : null;
        $product->save();

        return redirect()->route('admin.index', ['tab' => 'products'])->with('success', 'Product promotion eligibility updated.');
    }

    private function saveUser(Request $request): RedirectResponse
    {
        $id = $request->integer('id') ?: null;
        $user = $id ? User::query()->findOrFail($id) : new User;
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'alpha_dash', 'max:80', Rule::unique('users', 'username')->ignore($id)],
            'role' => ['required', Rule::in([User::ROLE_ADMIN, User::ROLE_WAREHOUSE, User::ROLE_POS])],
            'active' => ['sometimes', 'boolean'],
            'password' => [$id ? 'nullable' : 'required', 'string', 'min:10', 'confirmed'],
        ];
        $data = $request->validate($rules);
        if ($user->exists && $user->id === $request->user()->id && ! $request->boolean('active')) {
            throw ValidationException::withMessages(['active' => 'You cannot deactivate your own account.']);
        }
        $user->name = $data['name'];
        $user->username = $data['username'];
        $user->role = $data['role'];
        $user->active = $request->boolean('active');
        if (filled($data['password'] ?? null)) {
            $user->password = $data['password'];
        }
        $user->save();

        return redirect()->route('admin.index', ['tab' => 'users'])->with('success', 'User saved.');
    }

    private function deleteUser(Request $request): RedirectResponse
    {
        User::query()->findOrFail($request->integer('id'))->delete();

        return redirect()->route('admin.index', ['tab' => 'users'])->with('success', 'User deleted.');
    }

    private function saveQr(Request $request): RedirectResponse
    {
        $data = $request->validate(['qr_image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096']]);
        $setting = AppSetting::query()->firstOrNew(['key' => 'qr_image']);
        if ($setting->value) {
            Storage::disk('public')->delete($setting->value);
        }
        $setting->value = $request->file('qr_image')->store('qr', 'public');
        $setting->updated_by = $request->user()->id;
        $setting->updated_by_name = $request->user()->name;
        $setting->save();

        return redirect()->route('admin.index', ['tab' => 'qr'])->with('success', 'QR image updated.');
    }
}
