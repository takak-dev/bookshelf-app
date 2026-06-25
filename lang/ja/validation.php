<?php

return [
    'required' => ':attributeは必須です。',
    'string' => ':attributeは文字列で入力してください。',
    'email' => ':attributeは有効なメールアドレス形式で入力してください。',
    'unique' => 'その:attributeは既に使用されています。',
    'confirmed' => ':attributeが確認用と一致しません。',
    'date' => ':attributeは正しい日付形式で入力してください。',
    'after_or_equal' => ':attributeには:date以降の日付を指定してください。',
    'digits' => ':attributeは:digits桁の数字で入力してください。',
    'url' => ':attributeは正しいURL形式で入力してください。',
    'array' => ':attributeは配列で指定してください。',
    'integer' => ':attributeは整数で指定してください。',
    'exists' => '選択された:attributeは正しくありません。',
    'max' => [
        'string' => ':attributeは:max文字以内で入力してください。',
    ],
    'min' => [
        'string' => ':attributeは:min文字以上で入力してください。',
        'array' => ':attributeは:min個以上選択してください。',
    ],
    'between' => [
        'numeric' => ':attributeは:min〜:maxの範囲で指定してください。',
        'string' => ':attributeは:min〜:max文字で入力してください。'],

    // :date が "today" と英語表示されるのを避け、target_date は専用文言にする
    'custom' => [
        'target_date' => [
            'after_or_equal' => '期日は本日以降の日付を指定してください。',
        ],
    ],

    'attributes' => [
        'book_id' => '書籍',
        'target_date' => '期日',
        'name' => '名前',
        'email' => 'メールアドレス',
        'password' => 'パスワード',
        'title' => 'タイトル',
        'author' => '著者',
        'isbn' => 'ISBN',
        'published_date' => '出版日',
        'description' => '説明',
        'image_url' => '画像URL',
        'genres' => 'ジャンル',
        'rating' => '評価',
        'comment' => 'コメント',
    ],
];
