<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\Bank;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

class ImportCsvRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'file' => [
                'required',
                File::types(['csv', 'txt'])->max(10 * 1024),
            ],
            'bank' => [
                'nullable',
                Rule::enum(Bank::class),
            ],
        ];
    }
}
