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
            'noun' => 'required|string|max:100',
            'type' => 'nullable|string|max:100',
            'material_composition' => 'nullable|string|max:255',
            'key_attributes' => 'nullable|string|max:500',
            'size' => 'nullable|string|max:100',
            'net_content' => 'nullable|string|max:100',
            'pack' => 'nullable|string|max:100',
            'item_description' => 'nullable|string|max:500',
            'category_key' => 'required|exists:categories,key',
            'internal_category_id' => 'required|exists:internal_categories,id',
            'bizbox_item_code' => 'required|string|max:100',
            'philgeps_code' => 'nullable|string|max:100',
            'erp_item_code' => 'nullable|string|max:100|unique:products,erp_item_code',
            'unspsc_code' => 'nullable|string|max:50',
            'uom' => 'required|string|max:50',
            'unit_price' => 'nullable|numeric|min:0',
            'other_specs' => 'nullable|string',
            'tech_specs_pdf_path' => 'nullable|string|max:500',
            'price_change_reason' => 'nullable|string|max:255',
        ];
    }
}
