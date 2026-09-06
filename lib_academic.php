<?php
function academicTerms(string $level): array {
    return match(strtoupper($level)) {
        'HSSC' => ['Part-I','Part-II'],
        'BS' => ['1st Semester','2nd Semester','3rd Semester','4th Semester','5th Semester','6th Semester','7th Semester','8th Semester'],
        'ADP' => ['1st Year','2nd Year','1st Semester','2nd Semester','3rd Semester','4th Semester'],
        default => ['1st Semester']
    };
}
function termLabel(string $term): string { return $term; }
