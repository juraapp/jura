<?php

namespace App\Modules\Planning\Http\Requests\Wishlist;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWishlistItemRequest extends FormRequest
{
    use WishlistItemRules;

    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('wishlistItem'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->itemRules() + ['status' => ['nullable', 'in:wanted,dropped']];
    }
}
