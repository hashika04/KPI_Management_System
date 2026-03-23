<?php
include("../config/db.php");
echo "Connected successfully";
$cards = [
    [
        "title" => "With no title",
        "value" => "975,124",
        "change" => "+42.8% from previous week",
        "highlight" => false
    ],
    [
        "title" => "With title",
        "value" => "296,241",
        "change" => "+26.3% from previous week",
        "highlight" => false
    ],
    [
        "title" => "Company",
        "value" => "76,314",
        "change" => "+18.4% from previous week",
        "highlight" => true
    ]
];

$barData = [42, 78, 61, 98, 72, 89];
$months = ["Jan", "Feb", "Mar", "Apr", "Mai", "Jun"];

$heatmapRows = [
    "2pm"  => [2, 1, 3, 2, 4, 1, 2],
    "12pm" => [1, 2, 4, 3, 2, 3, 1],
    "10am" => [3, 2, 2, 4, 3, 2, 2],
    "8pm"  => [1, 3, 2, 2, 1, 4, 3]
];

$heatmapCols = ["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"];

$messages = [
    [
        "name" => "Theresa Webb",
        "text" => "Hi Robert, I'd like to invite you...",
        "time" => "3 min",
        "avatar" => "T"
    ],
    [
        "name" => "Marvin McKinney",
        "text" => "I've send you my portfolio, pl...",
        "time" => "13:07",
        "avatar" => "M"
    ],
    [
        "name" => "Jenny Wilson",
        "text" => "Can we review the engagement report?",
        "time" => "Jun 27",
        "avatar" => "J"
    ]
];
?>