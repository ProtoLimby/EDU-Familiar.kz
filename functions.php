
<?php
// functions.php

function calculateLevel($xp) {
    $base_xp = 1500; 
    $growth_factor = 1.2; 
    
    $level = 1;
    $xp_needed = $base_xp;
    
    while ($xp >= $xp_needed && $level < 100) {
        $xp -= $xp_needed;
        $level++;
        $xp_needed = floor($xp_needed * $growth_factor);
    }
    
    if ($level >= 100) {
        $level = 100;
        $xp = $xp_needed;
        $percent = 100;
    } else {
        $percent = ($xp / $xp_needed) * 100;
    }

    return [
        'level' => $level,
        'xp_current_level' => $xp,
        'xp_next_level' => $xp_needed,
        'progress' => round($percent, 1)
    ];
}

function generateRandomXP() {
    $rand = mt_rand(1, 100);
    if ($rand <= 50) return mt_rand(100, 200);      
    elseif ($rand <= 80) return mt_rand(201, 350);  
    elseif ($rand <= 95) return mt_rand(351, 450);  
    else return mt_rand(451, 500);                  
}

/**
 * Определяет УНИКАЛЬНУЮ тему и иконку для каждого предмета
 */
function getSubjectTheme($subjectName) {
    $s = mb_strtolower(trim($subjectName));
    
    // Дефолт
    $icon = 'fa-book';
    $theme = 'theme-default';

    // === ЯЗЫКИ ===
    if (strpos($s, 'русский') !== false) {
        $icon = 'fa-book-open'; $theme = 'theme-russian'; // Русский
    } elseif (strpos($s, 'казахский') !== false || strpos($s, 'қазақ') !== false) {
        $icon = 'fa-feather-alt'; $theme = 'theme-kazakh'; // Казахский
    } elseif (strpos($s, 'английский') !== false) {
        $icon = 'fa-language'; $theme = 'theme-english'; // Английский
    } 
    
    // === ТОЧНЫЕ НАУКИ ===
    elseif (strpos($s, 'алгебра') !== false) {
        $icon = 'fa-square-root-variable'; $theme = 'theme-math';
    } elseif (strpos($s, 'геометрия') !== false) {
        $icon = 'fa-shapes'; $theme = 'theme-math';
    } elseif (strpos($s, 'математика') !== false) {
        $icon = 'fa-calculator'; $theme = 'theme-math';
    } elseif (strpos($s, 'физика') !== false) {
        $icon = 'fa-atom'; $theme = 'theme-physics';
    } elseif (strpos($s, 'информатика') !== false) {
        $icon = 'fa-laptop-code'; $theme = 'theme-it';
    }

    // === ЕСТЕСТВЕННЫЕ НАУКИ ===
    elseif (strpos($s, 'химия') !== false) {
        $icon = 'fa-flask'; $theme = 'theme-chem';
    } elseif (strpos($s, 'биология') !== false) {
        $icon = 'fa-dna'; $theme = 'theme-bio';
    } elseif (strpos($s, 'естествознание') !== false) {
        $icon = 'fa-leaf'; $theme = 'theme-nature';
    }

    // === ГУМАНИТАРНЫЕ ===
    elseif (strpos($s, 'история казахстана') !== false) {
        $icon = 'fa-landmark'; $theme = 'theme-kaz-history';
    } elseif (strpos($s, 'история') !== false) {
        $icon = 'fa-hourglass-half'; $theme = 'theme-history';
    } elseif (strpos($s, 'география') !== false) {
        $icon = 'fa-globe-africa'; $theme = 'theme-geo';
    } elseif (strpos($s, 'право') !== false) {
        $icon = 'fa-balance-scale'; $theme = 'theme-law';
    } elseif (strpos($s, 'самопознание') !== false) {
        $icon = 'fa-hands-holding-circle'; $theme = 'theme-self';
    } elseif (strpos($s, 'глобальные') !== false) {
        $icon = 'fa-globe'; $theme = 'theme-global';
    }

    // === ИСКУССТВО И ТРУД ===
    elseif (strpos($s, 'труд') !== false || strpos($s, 'технолог') !== false) {
        $icon = 'fa-tools'; $theme = 'theme-work';
    } elseif (strpos($s, 'изобразительное') !== false || strpos($s, 'искусство') !== false) {
        $icon = 'fa-palette'; $theme = 'theme-art';
    } elseif (strpos($s, 'музыка') !== false) {
        $icon = 'fa-music'; $theme = 'theme-music';
    }

    // === СПОРТ И ВОЕНКА ===
    elseif (strpos($s, 'физкультура') !== false || strpos($s, 'физическая') !== false) {
        $icon = 'fa-running'; $theme = 'theme-sport';
    } elseif (strpos($s, 'военная') !== false || strpos($s, 'нвтп') !== false) {
        $icon = 'fa-shield-alt'; $theme = 'theme-military';
    }

    return ['icon' => $icon, 'style' => $theme];
}
?>
