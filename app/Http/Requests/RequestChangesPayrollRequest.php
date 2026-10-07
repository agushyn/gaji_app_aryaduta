<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RequestChangesPayrollRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'comment' => ['required', 'string', 'min:3', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'comment.required' => 'Alasan permintaan perbaikan wajib diisi.',
            'comment.min' => 'Alasan permintaan perbaikan minimal harus 3 karakter.',
            'comment.max' => 'Alasan permintaan perbaikan maksimal 1000 karakter.',
        ];
    }
}
