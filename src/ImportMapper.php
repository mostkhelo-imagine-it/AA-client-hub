<?php
declare(strict_types=1);

/**
 * Guesses which CSV column feeds which clients field, from the header
 * text alone — "Full Name", "Client Name", "E-mail Address", "Cell #",
 * etc. all resolve to the right target. This is a starting point, never
 * the final word: the mapping screen always shows the guess with every
 * column available to override, because no synonym list covers every
 * export a real CRM or spreadsheet produces.
 */
final class ImportMapper
{
    private const SYNONYMS = [
        'full_name' => ['fullname', 'name', 'clientname', 'contactname', 'customername'],
        'first_name' => ['firstname', 'fname', 'first', 'givenname'],
        'last_name' => ['lastname', 'lname', 'last', 'surname', 'familyname'],
        'email' => ['email', 'emailaddress', 'mail', 'e-mail'],
        'phone' => ['phone', 'phonenumber', 'mobile', 'mobilenumber', 'cell', 'telephone', 'tel', 'whatsapp'],
        'address' => ['address', 'addressline1', 'street', 'location', 'city'],
        'source' => ['source', 'leadsource', 'referral', 'channel'],
        'notes' => ['notes', 'note', 'comment', 'comments', 'remarks', 'description'],
        'tags' => ['tags', 'tag', 'labels', 'segments', 'lists', 'groups'],
    ];

    /**
     * @param array<int,string> $header
     * @return array<string,string|null> field => matched column name (or null)
     */
    public static function guess(array $header): array
    {
        $normalized = [];
        foreach ($header as $col) {
            $normalized[$col] = self::normalize($col);
        }

        $mapping = [];
        $claimed = [];

        foreach (self::SYNONYMS as $field => $synonyms) {
            $mapping[$field] = null;
            foreach ($header as $col) {
                if (isset($claimed[$col])) {
                    continue;
                }
                if (in_array($normalized[$col], $synonyms, true)) {
                    $mapping[$field] = $col;
                    $claimed[$col] = true;
                    break;
                }
            }
        }

        return $mapping;
    }

    private static function normalize(string $s): string
    {
        $s = mb_strtolower(trim($s));
        return preg_replace('/[^a-z0-9]/', '', $s) ?? '';
    }
}
