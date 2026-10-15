<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'item_description' => 'required|string|max:500',
            'category_key' => 'required|exists:categories,key',
            'internal_category_id' => 'required|exists:internal_categories,id',
            'bizbox_item_code' => 'required|string|max:100',
            'philgeps_code' => 'required|string|max:100',
            'erp_item_code' => 'nullable|string|max:100|unique:products,erp_item_code',
            'unspsc_code' => 'nullable|string|max:50',
            'generic_name' => 'nullable|string|max:255',
            'brand' => 'nullable|string|max:255',
            'uom' => 'required|string|max:50',
            'unit_price' => 'nullable|numeric|min:0',
            'specifications' => 'nullable|string',
            'remarks' => 'nullable|string',
        ];
    }
}
