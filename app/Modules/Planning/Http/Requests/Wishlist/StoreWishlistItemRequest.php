<?php

namespace App\Modules\Planning\Http\Requests\Wishlist;

use App\Modules\Planning\Models\WishlistItem;
use Illuminate\Foundation\Http\FormRequest;

class StoreWishlistItemRequest extends FormRequest
{
    use WishlistItemRules;

    public function authorize(): bool
    {
        return $this->user()->can('create', WishlistItem::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->itemRules();
    }
}
