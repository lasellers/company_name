<?php
// Note: Mulling ways to condense the sub functions. All of the string and array really
// need to be pre-processed and cached so we can ditch a lot of the preamble.

/**
 * Takes a company string and explodes it to a first,middle,last array
 * @param string $record
 * @return array
 */
function getFirstMiddleLast(string $record): array
{
    $words = explode(" ", trim($record));
    if (count($words) === 2) {
        $middle = "";
        [$first, $last] = $words;
    } else {
        [$first, $middle, $last] = $words;
    }
    return [$first, $middle, $last];
}

/**
 * Reverse the first and middle names
 * @param string $record
 * @return string
 */
function reverseFirstMiddleNames(string $record): string
{
    [$first, $middle, $last] = getFirstMiddleLast($record);
    return trim(implode(" ", [$middle, $first, $last]));
}

/**
 * @param array $aliases
 * @param string $record
 * @return bool
 */
function matchName(array $aliases, string $record): bool
{
    // prep data
    $words = explode(" ", $record);
    // Anything other than 2 or 3 words, fails
    if (!in_array(count($words), [2, 3], true))
        return false;

    $reverseRecord = reverseFirstMiddleNames($record);

    // if exact match, good enough
    if (exactMatch($aliases, $record) || exactMatch($aliases, $reverseRecord))
        return true;

    // Otherwise, we look for other kinds of matches
    // prep data
    $reverseWords = explode(" ", $reverseRecord);
    [$recordFirst, $recordMiddle, $recordLast] = getFirstMiddleLast($record);

    // if not an exact match, try seeing if there are partial matches ...
    // do this once per call
    $aliases3s = [];
    $aliases2s = [];
    foreach ($aliases as $alias) {
        $a = explode(" ", $alias);
        if (count($a) >= 3) {
            $aliases3s[] = $a;
        } else { // if (count($a) === 2) {
            $aliases2s[] = $a;
        }
    }

    // C. Check if we have middle initials that might match to the full
    // but not with reversed first+middle
    if (matchingMiddleInitial($aliases3s, $words))
        return true;

    // A. no middle name (on alias)
    if (middleNameMissingOnAlias($aliases2s, $recordFirst, $recordMiddle, $recordLast)
        || middleNameMissingOnAlias($aliases2s, $recordMiddle, $recordFirst, $recordLast))
        return true;

    // B. no middle name (on record)
    $aliasesFML = [];
    foreach ($aliases as $alias) {
        $aliasesFML[] = getFirstMiddleLast($alias);
    }
    if (middleNameMissingOnRecord($aliasesFML, $words)
        || middleNameMissingOnRecord($aliasesFML, $reverseWords))
        return true;

    return false;
}

/**
 * Looks for an exact match of record in the aliases array.
 * @param array $aliases
 * @param string $record
 * @return bool
 */
function exactMatch(array $aliases, string $record): bool
{
    if (in_array($record, $aliases, true)) {
        return true;
    }
    return false;
}

/**
 * If record or aliases are less than 3 than ignored (pass/true).
 * If either an alias item or record has an initial middle name that matches the first char of another,
 * that is a match/true.
 * Otherwise false.
 * @param array $aliases3s
 * @param array $words
 * @return bool
 */
function matchingMiddleInitial(array $aliases3s, array $words): bool
{
    if (count($words) === 2)
        return false;

    if (count($aliases3s) === count($words))
        return false;

    $middleRecord = $words[1];
    foreach ($aliases3s as $alias) {
        $middleAlias = $alias[1];
        // check if record has a matching initial
        if (strlen($middleRecord) === 1 && $middleRecord === $middleAlias[0]) {
            return true;
        }
        // and if any of the aliases do
        if (strlen($middleAlias) === 1 && $middleAlias === $middleRecord[0]) {
            return true;
        }
    }

    return false;
}

/**
 * Looks for a match where the middle name is missing (on record)
 * @param array $aliasesFML
 * @param array $words
 * @return bool
 */
function middleNameMissingOnRecord(array $aliasesFML, array $words): bool
{
    // if middle name is not missing, ignore
    if (count($words) !== 2)
        return false;

    //
    [$first, $last] = $words;
    foreach ($aliasesFML as $alias) {
        [$aliasFirst, $aliasMiddle, $aliasLast] = $alias;
        if (in_array($first, [$aliasFirst, $aliasMiddle], true) && $last === $aliasLast) {
            return true;
        }
    }

    return false;
}

/**
 * Looks for a match where the middle name is missing (on alias)
 * @param array $aliases2s
 * @param string $recordFirst
 * @param string $recordMiddle
 * @param string $recordLast
 * @return bool
 */
function middleNameMissingOnAlias(array $aliases2s, string $recordFirst, string $recordMiddle, string $recordLast): bool
{
    if (count($aliases2s) === 0)
        return false;

    if ($recordMiddle === '')
        return false;

    foreach ($aliases2s as $alias) {
        [$first, $last] = $alias;
        // for aliases: last name must match, and first name can be either first or middle of record
        if (in_array($first, [$recordFirst, $recordMiddle], true) && $last === $recordLast) {
            return true;
        }
    }

    return false;
}

//Testing Cases
assertTest(
    " A) Exact match",
    ["FIG WorldWide LLC", "Al LLC"],
    [
        "FIG WorldWide LLC" => true,
        "Al LLC" => true,
        "FIG Capital LLC" => false
    ]
);
assertTest(
    " B) Middle name missing (on alias):",
    ["FIG LLC"],
    [
        "FIG WorldWide LLC" => true,
        "FIG Finance LLC" => true,
        "Alexander LLC" => false
    ]
);
assertTest(
    " C) Middle name missing (on record name)",
    ["FIG WorldWide LLC"],
    [
        "FIG LLC" => true,
        "FIG Capital LLC" => false,
        "Alexander LLC" => false
    ]
);
assertTest(
    " D) More middle name tests:",
    ["FIG WorldWide LLC", "FIG Finance LLC"],
    [
        "FIG WorldWide LLC" => true,
        "FIG Finance LLC" => true,
        "Alexander LLC" => false
    ]
);
assertTest(
    " E) Middle initial matches middle name ",
    ["FIG WorldWide LLC", "FIG F LLC"],
    [
        "FIG W LLC" => true,
        "FIG Finance LLC" => true,
        "FIG E LLC" => false,
        "FIG Edward LLC" => false,
        "FIG Gregory LLC" => false,
    ]
);
assertTest(
    " # # # Bonus: Transposition
 \n F) First name and middle name can be transposed ",
    ["FIG Risk LLC"],
    [
        "Risk FIG LLC" => true,
        "FIG R LLC" => true,
        "Risk LLC" => true,
        "Risk Capital LLC" => false,
    ]
);
assertTest(
    " G) Last name cannot be transposed ",
    ["FIG WorldWide LLC"],
    [
        "FIG LLC WorldWide" => false,
        "LLC FIG WorldWide" => false,
        "LLC WorldWide" => false,
    ]
);
assertTest(
    " H) Errors with too little data",
    ["FIG", ""],
    [
        "FIG" => false,
        "" => false,
    ]
);

function assertTest($step, array $aliases, array $records)
{
    echo "\n" . $step;
    $i = 1;
    foreach ($records as $record => $expected) {
        $actual = matchName($aliases, $record);
        if ($actual !== $expected) {
            echo "\n $i. Expected : " . ($expected ? "true" : "false");
            echo "\n    Actual: " . ($actual ? "true" : "false");
            echo "\n    Record: '$record' or '" . reverseFirstMiddleNames($record) . "' ~== Aliases:" . implode(", ", $aliases);
        }
        $i++;
    }
    echo "\n ";
}
