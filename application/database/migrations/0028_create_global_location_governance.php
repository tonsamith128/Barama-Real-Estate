<?php

declare(strict_types=1);

/* BARAMA_GLOBAL_LOCATIONS_V101 */
return static function (PDO $pdo): void {
    $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS location_countries (
    country_code CHAR(2) NOT NULL,
    iso3 CHAR(3) NOT NULL,
    name VARCHAR(160) NOT NULL,
    native_name VARCHAR(160) NULL,
    enabled TINYINT(1) NOT NULL DEFAULT 0,
    is_default TINYINT(1) NOT NULL DEFAULT 0,
    sort_order INT NOT NULL DEFAULT 0,
    data_status VARCHAR(24) NOT NULL DEFAULT 'catalog_only',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (country_code),
    UNIQUE KEY location_countries_iso3_unique (iso3),
    KEY location_countries_enabled_index (enabled, is_default, sort_order, name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

    $countries = [
        ['AF', 'AFG', 'Afghanistan'],
        ['AL', 'ALB', 'Albania'],
        ['DZ', 'DZA', 'Algeria'],
        ['AS', 'ASM', 'American Samoa'],
        ['AD', 'AND', 'Andorra'],
        ['AO', 'AGO', 'Angola'],
        ['AI', 'AIA', 'Anguilla'],
        ['AQ', 'ATA', 'Antarctica'],
        ['AG', 'ATG', 'Antigua and Barbuda'],
        ['AR', 'ARG', 'Argentina'],
        ['AM', 'ARM', 'Armenia'],
        ['AW', 'ABW', 'Aruba'],
        ['AU', 'AUS', 'Australia'],
        ['AT', 'AUT', 'Austria'],
        ['AZ', 'AZE', 'Azerbaijan'],
        ['BS', 'BHS', 'Bahamas'],
        ['BH', 'BHR', 'Bahrain'],
        ['BD', 'BGD', 'Bangladesh'],
        ['BB', 'BRB', 'Barbados'],
        ['BY', 'BLR', 'Belarus'],
        ['BE', 'BEL', 'Belgium'],
        ['BZ', 'BLZ', 'Belize'],
        ['BJ', 'BEN', 'Benin'],
        ['BM', 'BMU', 'Bermuda'],
        ['BT', 'BTN', 'Bhutan'],
        ['BO', 'BOL', 'Bolivia, Plurinational State of'],
        ['BQ', 'BES', 'Bonaire, Sint Eustatius and Saba'],
        ['BA', 'BIH', 'Bosnia and Herzegovina'],
        ['BW', 'BWA', 'Botswana'],
        ['BV', 'BVT', 'Bouvet Island'],
        ['BR', 'BRA', 'Brazil'],
        ['IO', 'IOT', 'British Indian Ocean Territory'],
        ['BN', 'BRN', 'Brunei Darussalam'],
        ['BG', 'BGR', 'Bulgaria'],
        ['BF', 'BFA', 'Burkina Faso'],
        ['BI', 'BDI', 'Burundi'],
        ['CV', 'CPV', 'Cabo Verde'],
        ['KH', 'KHM', 'Cambodia'],
        ['CM', 'CMR', 'Cameroon'],
        ['CA', 'CAN', 'Canada'],
        ['KY', 'CYM', 'Cayman Islands'],
        ['CF', 'CAF', 'Central African Republic'],
        ['TD', 'TCD', 'Chad'],
        ['CL', 'CHL', 'Chile'],
        ['CN', 'CHN', 'China'],
        ['CX', 'CXR', 'Christmas Island'],
        ['CC', 'CCK', 'Cocos (Keeling) Islands'],
        ['CO', 'COL', 'Colombia'],
        ['KM', 'COM', 'Comoros'],
        ['CG', 'COG', 'Congo'],
        ['CD', 'COD', 'Congo, The Democratic Republic of the'],
        ['CK', 'COK', 'Cook Islands'],
        ['CR', 'CRI', 'Costa Rica'],
        ['HR', 'HRV', 'Croatia'],
        ['CU', 'CUB', 'Cuba'],
        ['CW', 'CUW', 'Curaçao'],
        ['CY', 'CYP', 'Cyprus'],
        ['CZ', 'CZE', 'Czechia'],
        ['CI', 'CIV', 'Côte d\'Ivoire'],
        ['DK', 'DNK', 'Denmark'],
        ['DJ', 'DJI', 'Djibouti'],
        ['DM', 'DMA', 'Dominica'],
        ['DO', 'DOM', 'Dominican Republic'],
        ['EC', 'ECU', 'Ecuador'],
        ['EG', 'EGY', 'Egypt'],
        ['SV', 'SLV', 'El Salvador'],
        ['GQ', 'GNQ', 'Equatorial Guinea'],
        ['ER', 'ERI', 'Eritrea'],
        ['EE', 'EST', 'Estonia'],
        ['SZ', 'SWZ', 'Eswatini'],
        ['ET', 'ETH', 'Ethiopia'],
        ['FK', 'FLK', 'Falkland Islands (Malvinas)'],
        ['FO', 'FRO', 'Faroe Islands'],
        ['FJ', 'FJI', 'Fiji'],
        ['FI', 'FIN', 'Finland'],
        ['FR', 'FRA', 'France'],
        ['GF', 'GUF', 'French Guiana'],
        ['PF', 'PYF', 'French Polynesia'],
        ['TF', 'ATF', 'French Southern Territories'],
        ['GA', 'GAB', 'Gabon'],
        ['GM', 'GMB', 'Gambia'],
        ['GE', 'GEO', 'Georgia'],
        ['DE', 'DEU', 'Germany'],
        ['GH', 'GHA', 'Ghana'],
        ['GI', 'GIB', 'Gibraltar'],
        ['GR', 'GRC', 'Greece'],
        ['GL', 'GRL', 'Greenland'],
        ['GD', 'GRD', 'Grenada'],
        ['GP', 'GLP', 'Guadeloupe'],
        ['GU', 'GUM', 'Guam'],
        ['GT', 'GTM', 'Guatemala'],
        ['GG', 'GGY', 'Guernsey'],
        ['GN', 'GIN', 'Guinea'],
        ['GW', 'GNB', 'Guinea-Bissau'],
        ['GY', 'GUY', 'Guyana'],
        ['HT', 'HTI', 'Haiti'],
        ['HM', 'HMD', 'Heard Island and McDonald Islands'],
        ['VA', 'VAT', 'Holy See (Vatican City State)'],
        ['HN', 'HND', 'Honduras'],
        ['HK', 'HKG', 'Hong Kong'],
        ['HU', 'HUN', 'Hungary'],
        ['IS', 'ISL', 'Iceland'],
        ['IN', 'IND', 'India'],
        ['ID', 'IDN', 'Indonesia'],
        ['IR', 'IRN', 'Iran, Islamic Republic of'],
        ['IQ', 'IRQ', 'Iraq'],
        ['IE', 'IRL', 'Ireland'],
        ['IM', 'IMN', 'Isle of Man'],
        ['IL', 'ISR', 'Israel'],
        ['IT', 'ITA', 'Italy'],
        ['JM', 'JAM', 'Jamaica'],
        ['JP', 'JPN', 'Japan'],
        ['JE', 'JEY', 'Jersey'],
        ['JO', 'JOR', 'Jordan'],
        ['KZ', 'KAZ', 'Kazakhstan'],
        ['KE', 'KEN', 'Kenya'],
        ['KI', 'KIR', 'Kiribati'],
        ['KP', 'PRK', 'Korea, Democratic People\'s Republic of'],
        ['KR', 'KOR', 'Korea, Republic of'],
        ['KW', 'KWT', 'Kuwait'],
        ['KG', 'KGZ', 'Kyrgyzstan'],
        ['LA', 'LAO', 'Lao People\'s Democratic Republic'],
        ['LV', 'LVA', 'Latvia'],
        ['LB', 'LBN', 'Lebanon'],
        ['LS', 'LSO', 'Lesotho'],
        ['LR', 'LBR', 'Liberia'],
        ['LY', 'LBY', 'Libya'],
        ['LI', 'LIE', 'Liechtenstein'],
        ['LT', 'LTU', 'Lithuania'],
        ['LU', 'LUX', 'Luxembourg'],
        ['MO', 'MAC', 'Macao'],
        ['MG', 'MDG', 'Madagascar'],
        ['MW', 'MWI', 'Malawi'],
        ['MY', 'MYS', 'Malaysia'],
        ['MV', 'MDV', 'Maldives'],
        ['ML', 'MLI', 'Mali'],
        ['MT', 'MLT', 'Malta'],
        ['MH', 'MHL', 'Marshall Islands'],
        ['MQ', 'MTQ', 'Martinique'],
        ['MR', 'MRT', 'Mauritania'],
        ['MU', 'MUS', 'Mauritius'],
        ['YT', 'MYT', 'Mayotte'],
        ['MX', 'MEX', 'Mexico'],
        ['FM', 'FSM', 'Micronesia, Federated States of'],
        ['MD', 'MDA', 'Moldova, Republic of'],
        ['MC', 'MCO', 'Monaco'],
        ['MN', 'MNG', 'Mongolia'],
        ['ME', 'MNE', 'Montenegro'],
        ['MS', 'MSR', 'Montserrat'],
        ['MA', 'MAR', 'Morocco'],
        ['MZ', 'MOZ', 'Mozambique'],
        ['MM', 'MMR', 'Myanmar'],
        ['NA', 'NAM', 'Namibia'],
        ['NR', 'NRU', 'Nauru'],
        ['NP', 'NPL', 'Nepal'],
        ['NL', 'NLD', 'Netherlands'],
        ['NC', 'NCL', 'New Caledonia'],
        ['NZ', 'NZL', 'New Zealand'],
        ['NI', 'NIC', 'Nicaragua'],
        ['NE', 'NER', 'Niger'],
        ['NG', 'NGA', 'Nigeria'],
        ['NU', 'NIU', 'Niue'],
        ['NF', 'NFK', 'Norfolk Island'],
        ['MK', 'MKD', 'North Macedonia'],
        ['MP', 'MNP', 'Northern Mariana Islands'],
        ['NO', 'NOR', 'Norway'],
        ['OM', 'OMN', 'Oman'],
        ['PK', 'PAK', 'Pakistan'],
        ['PW', 'PLW', 'Palau'],
        ['PS', 'PSE', 'Palestine, State of'],
        ['PA', 'PAN', 'Panama'],
        ['PG', 'PNG', 'Papua New Guinea'],
        ['PY', 'PRY', 'Paraguay'],
        ['PE', 'PER', 'Peru'],
        ['PH', 'PHL', 'Philippines'],
        ['PN', 'PCN', 'Pitcairn'],
        ['PL', 'POL', 'Poland'],
        ['PT', 'PRT', 'Portugal'],
        ['PR', 'PRI', 'Puerto Rico'],
        ['QA', 'QAT', 'Qatar'],
        ['RO', 'ROU', 'Romania'],
        ['RU', 'RUS', 'Russian Federation'],
        ['RW', 'RWA', 'Rwanda'],
        ['RE', 'REU', 'Réunion'],
        ['BL', 'BLM', 'Saint Barthélemy'],
        ['SH', 'SHN', 'Saint Helena, Ascension and Tristan da Cunha'],
        ['KN', 'KNA', 'Saint Kitts and Nevis'],
        ['LC', 'LCA', 'Saint Lucia'],
        ['MF', 'MAF', 'Saint Martin (French part)'],
        ['PM', 'SPM', 'Saint Pierre and Miquelon'],
        ['VC', 'VCT', 'Saint Vincent and the Grenadines'],
        ['WS', 'WSM', 'Samoa'],
        ['SM', 'SMR', 'San Marino'],
        ['ST', 'STP', 'Sao Tome and Principe'],
        ['SA', 'SAU', 'Saudi Arabia'],
        ['SN', 'SEN', 'Senegal'],
        ['RS', 'SRB', 'Serbia'],
        ['SC', 'SYC', 'Seychelles'],
        ['SL', 'SLE', 'Sierra Leone'],
        ['SG', 'SGP', 'Singapore'],
        ['SX', 'SXM', 'Sint Maarten (Dutch part)'],
        ['SK', 'SVK', 'Slovakia'],
        ['SI', 'SVN', 'Slovenia'],
        ['SB', 'SLB', 'Solomon Islands'],
        ['SO', 'SOM', 'Somalia'],
        ['ZA', 'ZAF', 'South Africa'],
        ['GS', 'SGS', 'South Georgia and the South Sandwich Islands'],
        ['SS', 'SSD', 'South Sudan'],
        ['ES', 'ESP', 'Spain'],
        ['LK', 'LKA', 'Sri Lanka'],
        ['SD', 'SDN', 'Sudan'],
        ['SR', 'SUR', 'Suriname'],
        ['SJ', 'SJM', 'Svalbard and Jan Mayen'],
        ['SE', 'SWE', 'Sweden'],
        ['CH', 'CHE', 'Switzerland'],
        ['SY', 'SYR', 'Syrian Arab Republic'],
        ['TW', 'TWN', 'Taiwan, Province of China'],
        ['TJ', 'TJK', 'Tajikistan'],
        ['TZ', 'TZA', 'Tanzania, United Republic of'],
        ['TH', 'THA', 'Thailand'],
        ['TL', 'TLS', 'Timor-Leste'],
        ['TG', 'TGO', 'Togo'],
        ['TK', 'TKL', 'Tokelau'],
        ['TO', 'TON', 'Tonga'],
        ['TT', 'TTO', 'Trinidad and Tobago'],
        ['TN', 'TUN', 'Tunisia'],
        ['TM', 'TKM', 'Turkmenistan'],
        ['TC', 'TCA', 'Turks and Caicos Islands'],
        ['TV', 'TUV', 'Tuvalu'],
        ['TR', 'TUR', 'Türkiye'],
        ['UG', 'UGA', 'Uganda'],
        ['UA', 'UKR', 'Ukraine'],
        ['AE', 'ARE', 'United Arab Emirates'],
        ['GB', 'GBR', 'United Kingdom'],
        ['US', 'USA', 'United States'],
        ['UM', 'UMI', 'United States Minor Outlying Islands'],
        ['UY', 'URY', 'Uruguay'],
        ['UZ', 'UZB', 'Uzbekistan'],
        ['VU', 'VUT', 'Vanuatu'],
        ['VE', 'VEN', 'Venezuela, Bolivarian Republic of'],
        ['VN', 'VNM', 'Viet Nam'],
        ['VG', 'VGB', 'Virgin Islands, British'],
        ['VI', 'VIR', 'Virgin Islands, U.S.'],
        ['WF', 'WLF', 'Wallis and Futuna'],
        ['EH', 'ESH', 'Western Sahara'],
        ['YE', 'YEM', 'Yemen'],
        ['ZM', 'ZMB', 'Zambia'],
        ['ZW', 'ZWE', 'Zimbabwe'],
        ['AX', 'ALA', 'Åland Islands'],
    ];

    $insert = $pdo->prepare(
        'INSERT INTO location_countries (country_code, iso3, name, enabled, is_default, data_status)
'
        . ' VALUES (:country_code, :iso3, :name, 0, 0, :data_status)
'
        . ' ON DUPLICATE KEY UPDATE iso3 = VALUES(iso3), name = VALUES(name)'
    );

    foreach ($countries as [$countryCode, $iso3, $name]) {
        $insert->execute([
            'country_code' => $countryCode,
            'iso3' => $iso3,
            'name' => $name,
            'data_status' => 'catalog_only',
        ]);
    }

    // Preserve the production footprint: enable any countries already used by
    // the existing canonical locations tree. No existing location row changes.
    $usedCodes = $pdo->query(
        "SELECT DISTINCT UPPER(country_code) FROM locations
"
        . " WHERE country_code IS NOT NULL AND country_code <> ''"
    )->fetchAll(PDO::FETCH_COLUMN);

    $enable = $pdo->prepare(
        'UPDATE location_countries SET enabled = 1, data_status = :data_status WHERE country_code = :country_code'
    );
    foreach ($usedCodes as $usedCode) {
        $code = strtoupper(trim((string) $usedCode));
        if (preg_match('/^[A-Z]{2}$/', $code) === 1) {
            $enable->execute(['country_code' => $code, 'data_status' => 'existing_tree']);
        }
    }

    $enabledCount = (int) $pdo->query(
        'SELECT COUNT(*) FROM location_countries WHERE enabled = 1'
    )->fetchColumn();

    if ($enabledCount === 0) {
        // Current Barama production data is Cambodia-first. Fresh commercial
        // installs will later select a primary country during Installer setup.
        $enable->execute(['country_code' => 'KH', 'data_status' => 'catalog_only']);
    }

    $defaultCount = (int) $pdo->query(
        'SELECT COUNT(*) FROM location_countries WHERE is_default = 1'
    )->fetchColumn();

    if ($defaultCount === 0) {
        $defaultCode = (string) $pdo->query(
            "SELECT country_code FROM location_countries
"
            . " WHERE enabled = 1 ORDER BY (country_code = 'KH') DESC, name ASC LIMIT 1"
        )->fetchColumn();

        if ($defaultCode !== '') {
            $statement = $pdo->prepare(
                'UPDATE location_countries SET is_default = CASE WHEN country_code = :country_code THEN 1 ELSE 0 END'
            );
            $statement->execute(['country_code' => $defaultCode]);
        }
    }

    $setting = $pdo->prepare(
        'INSERT INTO application_settings (setting_key, setting_value, value_type)
'
        . " VALUES ('locations.mode', 'selected', 'string')
"
        . ' ON DUPLICATE KEY UPDATE setting_key = VALUES(setting_key)'
    );
    $setting->execute();
};
