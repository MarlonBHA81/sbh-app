<?php

/*
 * Pragmatic subset of validation messages (the rules hit most often). Any key
 * not present here falls back to the English translation automatically.
 */

return [
    'required' => ':attribute ফিল্ডটি আবশ্যক।',
    'email' => ':attribute একটি বৈধ ইমেল ঠিকানা হতে হবে।',
    'min' => [
        'array' => ':attribute ফিল্ডে অন্তত :min টি আইটেম থাকতে হবে।',
        'file' => ':attribute ফিল্ডটি অন্তত :min কিলোবাইট হতে হবে।',
        'numeric' => ':attribute ফিল্ডটি অন্তত :min হতে হবে।',
        'string' => ':attribute ফিল্ডটি অন্তত :min অক্ষরের হতে হবে।',
    ],
    'max' => [
        'array' => ':attribute ফিল্ডে :max টির বেশি আইটেম থাকতে পারবে না।',
        'file' => ':attribute ফিল্ডটি :max কিলোবাইটের বেশি হতে পারবে না।',
        'numeric' => ':attribute ফিল্ডটি :max এর বেশি হতে পারবে না।',
        'string' => ':attribute ফিল্ডটি :max অক্ষরের বেশি হতে পারবে না।',
    ],
    'unique' => ':attribute ইতিমধ্যে ব্যবহৃত হয়েছে।',
    'confirmed' => ':attribute নিশ্চিতকরণ মিলছে না।',
];
