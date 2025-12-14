<?php
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Exception as SpreadsheetReaderException;

/**
 * Normalize phone to digits-only or return null if empty.
 */
function normalize_phone($raw) {
    $digits = preg_replace('/\D+/', '', (string)$raw);
    if ($digits === '') return null;
    return $digits;
}

/**
 * Normalize/slugify header into a key, same as you used.
 */
function normalize_header_key($field) {
    $normalized = strtolower(
        preg_replace('/_+/', '_', str_replace([' ', ':'], ['_', ''], $field))
    );
    // remove leading/trailing underscores
    $normalized = trim($normalized, '_');
    return $normalized;
}

/**
 * Parse coach_level raw string and produce:
 *  - gca_coach_lvl: 1|2|3 or null (if blank or pending)
 *  - gca_coach_status: human status (Issued / Inactive - higher level achieved / Pending Requirements) or null
 *
 * Rule per your spec:
 *  - If coach_level blank => gca_coach_lvl = null
 *  - If contains "pending" => gca_coach_lvl = null (status = 'Pending Requirements')
 *  - Otherwise, set numeric level when found and status based on keywords
 */
function parse_gca_coach($raw) {
    $rawLower = strtolower(trim((string)$raw));
    $result = ['gca_coach_lvl' => null, 'gca_coach_status' => null];

    if ($rawLower === '') return $result;

    // detect numeric level first
    if (preg_match('/\b(?:coach\s*level\s*)?([1-3])\b/', $rawLower, $m)) {
        $level = (int)$m[1];
    } elseif (preg_match('/\b(level|lvl)\s*[:\s]*([1-3])\b/', $rawLower, $m2)) {
        $level = (int)$m2[2];
    } else {
        $level = null;
    }

    // detect status keywords
    $status = null;
    if (strpos($rawLower, 'issued') !== false) {
        $status = 'Issued';
    } elseif (strpos($rawLower, 'inactive') !== false) {
        $status = 'Inactive - higher level achieved';
    } elseif (strpos($rawLower, 'pending') !== false) {
        $status = 'Pending Requirements';
    }

    // Per your rule: if pending -> set level to null
    if ($status === 'Pending Requirements') {
        $level = null;
    }

    $result['gca_coach_lvl'] = $level !== null ? $level : null;
    $result['gca_coach_status'] = $status;

    return $result;
}

/**
 * loadStructuredUsers
 *  - reads two sheets (AthleteList, CoachList)
 *  - returns an array of user arrays with DB-ready keys:
 *    first_name, last_name, email, role_level, full_name,
 *    gca_coach_lvl (1|2|3|null),
 *    gca_coach_status (may be null),
 *    phone_number,
 *    emergency_contact_name,
 *    emergency_contact_phone,
 *    medical_info,
 *    raw_data (original normalized values)
 */
function loadStructuredUsers($filepath) {

    $countDone = 0;

    if (!file_exists($filepath)) {
        throw new Exception("Spreadsheet file not found at path: $filepath");
    }
    if (!is_readable($filepath)) {
        throw new Exception("Spreadsheet file is not readable: $filepath");
    }

    $sheetNamesToProcess = ['AthleteList', 'CoachList'];
    $allUsers = [];

    try {
        $spreadsheet = IOFactory::load($filepath);
    } catch (SpreadsheetReaderException $e) {
        throw new Exception("Error loading spreadsheet: " . $e->getMessage());
    }

    foreach ($sheetNamesToProcess as $sheetName) {
        $sheet = $spreadsheet->getSheetByName($sheetName);
        if (!$sheet) {
            // skip missing sheet but continue other sheets
            continue;
        }

        // preserve A,B,C style keys
        $rows = $sheet->toArray(null, true, true, true);

        

        if (empty($rows) || !isset($rows[1])) {
            continue;
        }

        // Build a normalized headers map: column letter => normalized_key
        $headersRaw = $rows[1];
        $headers = [];
        foreach ($headersRaw as $col => $field) {
            $headers[$col] = normalize_header_key(trim((string)$field));
        }

        file_put_contents(
                '/home/automaddic/mtb/server/scripts/debug.txt',
                "row: " . print_r($headers, true) . "\n",
                FILE_APPEND
            );

        $roleLevel = ($sheetName === 'CoachList') ? 2 : 1;

        $maxRow = count($rows);
        for ($i = 2; $i <= $maxRow; $i++) {
            $row = $rows[$i];
            $countDone += 1;

            // map normalized fields => values
            $userRaw = [];
            foreach ($headers as $col => $key) {
                $val = isset($row[$col]) ? trim((string)$row[$col]) : '';
                $userRaw[$key] = $val;
            }

            // require at least a name or email
            if (empty($userRaw['first_name']) && empty($userRaw['last_name']) && empty($userRaw['email'])) {
                continue;
            }

            // -----------------------
            // compute GCA coach info
            // -----------------------
            $coachParse = parse_gca_coach($userRaw['coach_level'] ?? '');

            // -----------------------
            // phone + emergency contact
            // Preference order for emergency contact:
            // 1) emergency_contact_first_name / emergency_contact_last_name / emergency_contact_cell_phone_number
            // 2) parent_1_first_name / parent_1_last_name / parent_1_cell_phone (fallback)
            // Registrant phone: registrant_telephone (or alternative keys)
            // -----------------------
            $ec_first = trim($userRaw['emergency_contact_first_name'] ?? '');
            $ec_last  = trim($userRaw['emergency_contact_last_name'] ?? '');
            $ec_phone_raw = trim($userRaw['emergency_contact_cell_phone_number'] ?? '');

            if ($ec_first === '' && $ec_last === '' && $ec_phone_raw === '') {
                // fallback to parent_1_*
                $ec_first = trim($userRaw['parent_1_first_name'] ?? $userRaw['parent_1_name'] ?? '');
                $ec_last  = trim($userRaw['parent_1_last_name'] ?? '');
                $ec_phone_raw = trim($userRaw['parent_1_cell_phone'] ?? $userRaw['parent_1_cell_phone_number'] ?? '');
            }

            $ec_name = trim(($ec_first ?: '') . ' ' . ($ec_last ?: ''));
            $ec_name = $ec_name === '' ? null : $ec_name;
            $ec_phone = normalize_phone($ec_phone_raw);

            // registrant phone variants
            $phone_candidates = [
                'registrant_telephone',
                'registrant_telephone_number',
                'registrant telephone',
                'phone',
                'phone_number',
                'telephone'
            ];
            $phone_number = null;
            foreach ($phone_candidates as $k) {
                if (!empty($userRaw[$k])) {
                    $phone_number = normalize_phone($userRaw[$k]);
                    if ($phone_number) break;
                }
            }

            // -----------------------
            // medical info consolidation
            // fields of interest (normalized header guesses):
            //  - has_medical_conditions_or_allergies
            //  - has_and_manages_the_following_medical_conditions_or_allergies
            //  - has_asthma_and_will_have_an_inhaler
            //  - more_information_(asthma) OR more_information_asthma
            //  - prescription_medication
            //  - more_information_(medication) OR more_information_medication
            // Rule: if a 'Has X' is 'no', do NOT include its more information field
            // -----------------------
            $med_parts = [];

            $has_med_any = strtolower(trim($userRaw['has_medical_conditions_or_allergies'] ?? ''));
            if ($has_med_any !== '') {
                $med_parts[] = "Has medical conditions/allergies: " . ucfirst($has_med_any);
            }

            $manages = trim($userRaw['has_and_manages_the_following_medical_conditions_or_allergies'] ?? '');
            if ($manages !== '') {
                $med_parts[] = "Conditions managed: " . $manages;
            }

            $has_asthma = strtolower(trim($userRaw['has_asthma_and_will_have_an_inhaler'] ?? ''));
            if ($has_asthma !== '') {
                $med_parts[] = "Has asthma / will have inhaler: " . ucfirst($has_asthma);
                if ($has_asthma !== 'no') {
                    $asthma_more = trim($userRaw['more_information_(asthma)'] ?? $userRaw['more_information_asthma'] ?? '');
                    if ($asthma_more !== '') {
                        $med_parts[] = "Asthma details: " . $asthma_more;
                    }
                }
            }

            $presc = strtolower(trim($userRaw['prescription_medication'] ?? ''));
            if ($presc !== '') {
                $med_parts[] = "Prescription medication: " . ucfirst($presc);
                if ($presc !== 'no') {
                    $med_more = trim($userRaw['more_information_(medication)'] ?? $userRaw['more_information_medication'] ?? '');
                    if ($med_more !== '') {
                        $med_parts[] = "Medication details: " . $med_more;
                    }
                }
            }

            $medical_info = null;
            if (!empty($med_parts)) {
                // combine with newline (or use ' | ' if you prefer single-line)
                $medical_info = implode("\n", $med_parts);
            } else {

                $medical_info = "No Medical Complications or Information Provided";

            }

            // -----------------------
            // Build final user record (DB-ready keys)
            // -----------------------
            $record = [
                'first_name' => $userRaw['first_name'] ?? '',
                'last_name'  => $userRaw['last_name'] ?? '',
                'email'      => $userRaw['email'] ?? '',
                'role_level' => $roleLevel,
                'full_name'  => trim(($userRaw['first_name'] ?? '') . ' ' . ($userRaw['last_name'] ?? '')),
                'gca_coach_lvl' => $coachParse['gca_coach_lvl'],          // 1|2|3|null
                'gca_coach_status' => $coachParse['gca_coach_status'],    // may be null
                'phone_number' => $phone_number,                         // normalized digits or null
                'emergency_contact_name' => $ec_name,
                'emergency_contact_phone' => $ec_phone,
                'medical_info' => $medical_info,
                'raw_data' => $userRaw
            ];




            $allUsers[] = $record;
        } // end rows loop
    } // end sheets loop

    return $allUsers;
}
