<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | The following language lines contain the default error messages used by
    | the validator class. Some of these rules have multiple versions such
    | as the size rules. Feel free to tweak each of these messages here.
    |
    */

    'accepted'             => '必須接受 :attribute 。',
    'accepted_if'          => '當 :other 為 :value 時，:attribute 必須被接受。',
    'active_url'           => ':attribute 不是一個有效的網址。',
    'after'                => ':attribute 必須要晚於 :date。',
    'after_or_equal'       => ':attribute 必須要等於或晚於 :date。',
    'alpha'                => ':attribute 只能包含字母。',
    'alpha_dash'           => ':attribute 只能包含字母、數字、破折號與底線。',
    'alpha_num'            => ':attribute 只能包含字母與數字。',
    'array'                => ':attribute 必須為陣列。',
    'before'               => ':attribute 必須要早於 :date。',
    'before_or_equal'      => ':attribute 必須要等於或早於 :date。',
    'between'              => [
        'numeric' => ':attribute 必須介於 :min 至 :max 之間。',
        'file'    => ':attribute 必須介於 :min 至 :max KB 之間。',
        'string'  => ':attribute 必須介於 :min 至 :max 個字元之間。',
        'array'   => ':attribute 必須包含 :min 至 :max 個項目。',
    ],
    'boolean'              => ':attribute 欄位必須為 true 或 false。',
    'confirmed'            => ':attribute 與確認欄位不相符。',
    'current_password'     => '密碼不正確。',
    'date'                 => ':attribute 不是一個有效的日期。',
    'date_equals'          => ':attribute 必須等於 :date。',
    'date_format'          => ':attribute 與格式 :format 不相符。',
    'declined'             => ':attribute 必須拒絕。',
    'declined_if'          => '當 :other 為 :value 時，:attribute 必須拒絕。',
    'different'            => ':attribute 與 :other 必須不同。',
    'digits'               => ':attribute 必須為 :digits 位數字。',
    'digits_between'       => ':attribute 必須介於 :min 至 :max 位數字。',
    'dimensions'           => ':attribute 圖片尺寸不正確。',
    'distinct'             => ':attribute 欄位有重複值。',
    'email'                => ':attribute 必須為有效的電子郵件地址。',
    'ends_with'            => ':attribute 必須以下列其中一個結尾：:values。',
    'exists'               => '選擇的 :attribute 無效。',
    'file'                 => ':attribute 必須為檔案。',
    'filled'               => ':attribute 欄位為必填。',
    'gt'                   => [
        'numeric' => ':attribute 必須大於 :value。',
        'file'    => ':attribute 必須大於 :value KB。',
        'string'  => ':attribute 必須多於 :value 個字元。',
        'array'   => ':attribute 必須多於 :value 個項目。',
    ],
    'gte'                  => [
        'numeric' => ':attribute 必須大於或等於 :value。',
        'file'    => ':attribute 必須大於或等於 :value KB。',
        'string'  => ':attribute 必須多於或等於 :value 個字元。',
        'array'   => ':attribute 必須多於或等於 :value 個項目。',
    ],
    'image'                => ':attribute 必須為圖片。',
    'in'                   => '選擇的 :attribute 無效。',
    'in_array'             => ':attribute 不在 :other 中。',
    'integer'              => ':attribute 必須為整數。',
    'ip'                   => ':attribute 必須為有效的 IP 位址。',
    'ipv4'                 => ':attribute 必須為有效的 IPv4 位址。',
    'ipv6'                 => ':attribute 必須為有效的 IPv6 位址。',
    'json'                 => ':attribute 必須為有效的 JSON 字串。',
    'lt'                   => [
        'numeric' => ':attribute 必須小於 :value。',
        'file'    => ':attribute 必須小於 :value KB。',
        'string'  => ':attribute 必須少於 :value 個字元。',
        'array'   => ':attribute 必須少於 :value 個項目。',
    ],
    'lte'                  => [
        'numeric' => ':attribute 必須小於或等於 :value。',
        'file'    => ':attribute 必須小於或等於 :value KB。',
        'string'  => ':attribute 必須少於或等於 :value 個字元。',
        'array'   => ':attribute 必須少於或等於 :value 個項目。',
    ],
    'max'                  => [
        'numeric' => ':attribute 不能大於 :max。',
        'file'    => ':attribute 不能大於 :max KB。',
        'string'  => ':attribute 不能大於 :max 個字元。',
        'array'   => ':attribute 最多不能超過 :max 個項目。',
    ],
    'mimes'                => ':attribute 必須為檔案類型：:values。',
    'mimetypes'            => ':attribute 必須為檔案類型：:values。',
    'min'                  => [
        'numeric' => ':attribute 不能小於 :min。',
        'file'    => ':attribute 不能小於 :min KB。',
        'string'  => ':attribute 不能少於 :min 個字元。',
        'array'   => ':attribute 至少要有 :min 個項目。',
    ],
    'multiple_of'          => ':attribute 必須為 :value 的倍數。',
    'not_in'               => '選擇的 :attribute 無效。',
    'not_regex'            => ':attribute 的格式無效。',
    'numeric'              => ':attribute 必須為數值。',
    'password'             => '密碼不正確。',
    'present'              => ':attribute 欄位必須存在。',
    'prohibited'           => ':attribute 欄位被禁止。',
    'prohibited_if'        => '當 :other 為 :value 時，:attribute 欄位被禁止。',
    'prohibited_unless'    => ':attribute 欄位被禁止，除非 :other 在 :values 中。',
    'prohibits'            => ':attribute 欄位禁止 :other 出現。',
    'regex'                => ':attribute 的格式無效。',
    'required'             => ':attribute 欄位為必填。',
    'required_if'          => '當 :other 為 :value 時，:attribute 欄位為必填。',
    'required_unless'      => ':attribute 欄位為必填，除非 :other 在 :values 中。',
    'required_with'        => '當 :values 出現時，:attribute 欄位為必填。',
    'required_with_all'    => '當 :values 出現時，:attribute 欄位為必填。',
    'required_without'     => '當 :values 不出現時，:attribute 欄位為必填。',
    'required_without_all' => '當 :values 都不出現時，:attribute 欄位為必填。',
    'same'                 => ':attribute 與 :other 必須相同。',
    'size'                 => [
        'numeric' => ':attribute 必須為 :size。',
        'file'    => ':attribute 必須為 :size KB。',
        'string'  => ':attribute 必須為 :size 個字元。',
        'array'   => ':attribute 必須包含 :size 個項目。',
    ],
    'starts_with'          => ':attribute 必須以下列之一開始：:values。',
    'string'               => ':attribute 必須為字串。',
    'timezone'             => ':attribute 必須為有效的時區。',
    'unique'               => ':attribute 已被使用。',
    'uploaded'             => ':attribute 上傳失敗。',
    'url'                  => ':attribute 格式不正確。',
    'uuid'                 => ':attribute 必須為有效的 UUID。',

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | Here you may specify custom validation messages for attributes using the
    | convention "attribute.rule" to name the lines. This makes it quick to
    | specify a specific custom language line for a given attribute rule.
    |
    */

    'custom' => [
        'attribute-name' => [
            'rule-name' => '自訂錯誤訊息',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Attributes
    |--------------------------------------------------------------------------
    |
    | The following language lines are used to swap attribute place-holders
    | with something more reader friendly such as "E-Mail Address" instead
    | of "email". This simply helps us make messages a little cleaner.
    |
    */

    'attributes' => [],
];
