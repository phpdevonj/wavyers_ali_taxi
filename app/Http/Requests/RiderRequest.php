<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;


class RiderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $method = strtolower($this->method());
        $user_id = $this->route()->rider;

        $rules = [];
        switch ($method) {
            case 'post':
                $rules = [
                    'username' => ['required', Rule::unique('users', 'username')->whereNull('deleted_at')],
                    'password' => 'required|min:8',
                    'email' => ['required', 'email', Rule::unique('users', 'email')->whereNull('deleted_at')],
                    'contact_number' => ['max:20', Rule::unique('users', 'contact_number')->whereNull('deleted_at')],
                ];
                break;
            case 'patch':
                $rules = [
                    'username'  => ['required', Rule::unique('users', 'username')->ignore($user_id)->whereNull('deleted_at')],
                    'email'     => ['required', 'email', Rule::unique('users', 'email')->ignore($user_id)->whereNull('deleted_at')],
                    'contact_number' => ['max:20', Rule::unique('users', 'contact_number')->ignore($user_id)->whereNull('deleted_at')],
                ];
                break;
        }

        return $rules;
    }

    public function messages()
    {
        return [
            'userProfile.dob.*'  =>'DOB is required.',
        ];
    }

     /**
     * @param Validator $validator
     */
    protected function failedValidation(Validator $validator) {
        $data = [
            'status' => true,
            'message' => $validator->errors()->first(),
            'all_message' =>  $validator->errors()
        ];

        if ( request()->is('api*')){
           throw new HttpResponseException( response()->json($data,422) );
        }

        if ($this->ajax()) {
            throw new HttpResponseException(response()->json($data,422));
        } else {
            throw new HttpResponseException(redirect()->back()->withInput()->with('errors', $validator->errors()));
        }
    }
}
