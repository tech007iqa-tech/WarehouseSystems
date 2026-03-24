<?php
/**
 * db_reset_seed.php
 * Destructive script to clear the labels database and seed it with the user's 
 * specified inventory templates for rapid intake.
 */

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/hardware_mapping.php';

echo "--- DATABASE RESET & SEED START ---\n";

try {
    // 1. TRUNCATE DATABASE (Wipe all previous items)
    $pdo_labels->exec("DELETE FROM items");
    $pdo_labels->exec("DELETE FROM sqlite_sequence WHERE name='items'"); // Reset auto-increment
    echo "✅ Database 'labels.sqlite' has been wiped clean.\n";

    // 2. PREPARE SEED DATA (Cleaned from user list)
   

/**
 * Updated HP Laptop Seed Data
 * Includes EliteBook, ProBook (including 640/650), ZBook, Pavilion, Envy, Spectre, Omen, and Victus series.
 * Mapped to accurate processor generations.
 */

$seed_data = [
    // --- EliteBook 830 Series (13.3" Mainstream Business) ---
    ['HP', 'EliteBook', '830 G5', 'i5-8th Gen'],
    ['HP', 'EliteBook', '830 G6', 'i5-8th Gen'],
    ['HP', 'EliteBook', '830 G7', 'i5-10th Gen'],
    ['HP', 'EliteBook', '830 G8', 'i5-11th Gen'],
    ['HP', 'EliteBook', '830 G9', 'i5-12th Gen'],
    ['HP', 'EliteBook', '830 G10', 'i5-13th Gen'],
    ['HP', 'EliteBook', '830 G11', 'Core Ultra 5'],

    // --- EliteBook 840 Series (14" Mainstream Business) ---
    ['HP', 'EliteBook', '840 G1', 'i5-4th Gen'],
    ['HP', 'EliteBook', '840 G2', 'i5-5th Gen'],
    ['HP', 'EliteBook', '840 G3', 'i5-6th Gen'],
    ['HP', 'EliteBook', '840 G4', 'i5-7th Gen'],
    ['HP', 'EliteBook', '840 G5', 'i5-8th Gen'],
    ['HP', 'EliteBook', '840 G6', 'i5-8th Gen'],
    ['HP', 'EliteBook', '840 G7', 'i5-10th Gen'],
    ['HP', 'EliteBook', '840 G8', 'i5-11th Gen'],
    ['HP', 'EliteBook', '840 G9', 'i5-12th Gen'],
    ['HP', 'EliteBook', '840 G10', 'i5-13th Gen'],
    ['HP', 'EliteBook', '840 G11', 'Core Ultra 5'],

    // --- EliteBook 860 Series (16" Mainstream Business) ---
    ['HP', 'EliteBook', '860 G9', 'i7-12th Gen'],
    ['HP', 'EliteBook', '860 G10', 'i7-13th Gen'],
    ['HP', 'EliteBook', '860 G11', 'Core Ultra 7'],

    // --- EliteBook 600 Series (Value Business - Rebranded from ProBook 600) ---
    ['HP', 'EliteBook', '640 G9', 'i5-12th Gen'],
    ['HP', 'EliteBook', '640 G10', 'i5-13th Gen'],
    ['HP', 'EliteBook', '640 G11', 'Core Ultra 5'],
    ['HP', 'EliteBook', '650 G9', 'i5-12th Gen'],
    ['HP', 'EliteBook', '650 G10', 'i5-13th Gen'],
    ['HP', 'EliteBook', '650 G11', 'Core Ultra 5'],

    // --- ProBook 640 Series (14" Professional Business) ---
    ['HP', 'ProBook', '640 G1', 'i5-4th Gen'],
    ['HP', 'ProBook', '640 G2', 'i5-6th Gen'],
    ['HP', 'ProBook', '640 G3', 'i5-7th Gen'],
    ['HP', 'ProBook', '640 G4', 'i5-8th Gen'],
    ['HP', 'ProBook', '640 G5', 'i5-8th Gen'],
    ['HP', 'ProBook', '640 G8', 'i5-11th Gen'],

    // --- ProBook 650 Series (15.6" Professional Business) ---
    ['HP', 'ProBook', '650 G1', 'i5-4th Gen'],
    ['HP', 'ProBook', '650 G2', 'i5-6th Gen'],
    ['HP', 'ProBook', '650 G3', 'i5-7th Gen'],
    ['HP', 'ProBook', '650 G4', 'i5-8th Gen'],
    ['HP', 'ProBook', '650 G5', 'i5-8th Gen'],
    ['HP', 'ProBook', '650 G8', 'i5-11th Gen'],

    // --- ProBook 440 Series (14" Essential Business) ---
    ['HP', 'ProBook', '440 G1', 'i5-4th Gen'],
    ['HP', 'ProBook', '440 G2', 'i5-5th Gen'],
    ['HP', 'ProBook', '440 G3', 'i5-6th Gen'],
    ['HP', 'ProBook', '440 G4', 'i5-7th Gen'],
    ['HP', 'ProBook', '440 G5', 'i5-8th Gen'],
    ['HP', 'ProBook', '440 G6', 'i5-8th Gen'],
    ['HP', 'ProBook', '440 G7', 'i5-10th Gen'],
    ['HP', 'ProBook', '440 G8', 'i5-11th Gen'],
    ['HP', 'ProBook', '440 G9', 'i5-12th Gen'],
    ['HP', 'ProBook', '440 G10', 'i5-13th Gen'],
    ['HP', 'ProBook', '440 G11', 'Core Ultra 5'],

    // --- ProBook 450 Series (15.6" Essential Business) ---
    ['HP', 'ProBook', '450 G1', 'i5-4th Gen'],
    ['HP', 'ProBook', '450 G2', 'i5-5th Gen'],
    ['HP', 'ProBook', '450 G3', 'i5-6th Gen'],
    ['HP', 'ProBook', '450 G4', 'i5-7th Gen'],
    ['HP', 'ProBook', '450 G5', 'i5-8th Gen'],
    ['HP', 'ProBook', '450 G6', 'i5-8th Gen'],
    ['HP', 'ProBook', '450 G7', 'i5-10th Gen'],
    ['HP', 'ProBook', '450 G8', 'i5-11th Gen'],
    ['HP', 'ProBook', '450 G9', 'i5-12th Gen'],
    ['HP', 'ProBook', '450 G10', 'i5-13th Gen'],
    ['HP', 'ProBook', '450 G11', 'Core Ultra 5'],

    // --- ZBook Firefly (Thin & Light Workstation) ---
    ['HP', 'ZBook Firefly', '14 G7', 'i7-10th Gen'],
    ['HP', 'ZBook Firefly', '14 G8', 'i7-11th Gen'],
    ['HP', 'ZBook Firefly', '14 G9', 'i7-12th Gen'],
    ['HP', 'ZBook Firefly', '14 G10', 'i7-13th Gen'],
    ['HP', 'ZBook Firefly', '14 G11', 'Core Ultra 7'],
    ['HP', 'ZBook Firefly', '16 G9', 'i7-12th Gen'],
    ['HP', 'ZBook Firefly', '16 G10', 'i7-13th Gen'],
    ['HP', 'ZBook Firefly', '16 G11', 'Core Ultra 7'],

    // --- ZBook Power (Value Workstation) ---
    ['HP', 'ZBook Power', 'G7', 'i7-10th Gen'],
    ['HP', 'ZBook Power', 'G8', 'i7-11th Gen'],
    ['HP', 'ZBook Power', 'G9', 'i7-12th Gen'],
    ['HP', 'ZBook Power', 'G10', 'i7-13th Gen'],
    ['HP', 'ZBook Power', 'G11', 'Core Ultra 7'],

    // --- ZBook Fury (Ultimate Workstation) ---
    ['HP', 'ZBook Fury', '15 G7', 'i7-10th Gen'],
    ['HP', 'ZBook Fury', '15 G8', 'i7-11th Gen'],
    ['HP', 'ZBook Fury', '16 G9', 'i7-12th Gen'],
    ['HP', 'ZBook Fury', '16 G10', 'i7-13th Gen'],
    ['HP', 'ZBook Fury', '16 G11', 'Core Ultra 9'],

    // --- Pavilion Series (Everyday Consumer) ---
    ['HP', 'Pavilion', '14-ce', 'i5-8th Gen'],
    ['HP', 'Pavilion', '14-dv', 'i5-11th Gen'],
    ['HP', 'Pavilion', '15-cs', 'i5-10th Gen'],
    ['HP', 'Pavilion', '15-eg', 'i5-11th Gen'],
    ['HP', 'Pavilion x360', '14-dw', 'i5-10th Gen'],
    ['HP', 'Pavilion x360', '14-dy', 'i5-11th Gen'],
    ['HP', 'Pavilion x360', '14-ek', 'i5-12th Gen'],

    // --- Envy Series (Premium Consumer) ---
    ['HP', 'Envy', '13-ba', 'i5-10th Gen'],
    ['HP', 'Envy', '13-aq', 'i5-8th Gen'],
    ['HP', 'Envy', '14-eb', 'i7-11th Gen'],
    ['HP', 'Envy', '15-ep', 'i7-10th Gen'],
    ['HP', 'Envy x360', '13-ay', 'Ryzen 5 4500U'],
    ['HP', 'Envy x360', '13-bd', 'i5-11th Gen'],
    ['HP', 'Envy x360', '15-ee', 'Ryzen 5 4500U'],
    ['HP', 'Envy x360', '15-ed', 'i7-10th Gen'],
    ['HP', 'Envy x360', '15-es', 'i7-11th Gen'],
    ['HP', 'Envy x360', '15-ew', 'i7-12th Gen'],
    ['HP', 'Envy x360', '15-fe', 'i7-13th Gen'],

    // --- Spectre Series (Ultra-Premium) ---
    ['HP', 'Spectre x360', '13-ae', 'i7-8th Gen'],
    ['HP', 'Spectre x360', '13-aw', 'i7-10th Gen'],
    ['HP', 'Spectre x360', '14-ea', 'i7-11th Gen'],
    ['HP', 'Spectre x360', '14-ef', 'i7-12th Gen'],
    ['HP', 'Spectre x360', '14-eu', 'Core Ultra 7'],
    ['HP', 'Spectre x360', '15-df', 'i7-9th Gen'],
    ['HP', 'Spectre x360', '16-f', 'i7-11th Gen'],

    // --- Omen Series (High-End Gaming) ---
    ['HP', 'Omen', '15-dc', 'i7-8th Gen'],
    ['HP', 'Omen', '15-ek', 'i7-10th Gen'],
    ['HP', 'Omen', '16-b', 'i7-11th Gen'],
    ['HP', 'Omen', '16-k', 'i7-12th Gen'],
    ['HP', 'Omen', '16-wf', 'i7-13th Gen'],
    ['HP', 'Omen', '17-ck', 'i7-12th Gen'],

    // --- Victus Series (Value Gaming) ---
    ['HP', 'Victus', '15-fa', 'i5-12th Gen'],
    ['HP', 'Victus', '15-fb', 'Ryzen 5 5600H'],
    ['HP', 'Victus', '16-d', 'i7-11th Gen'],
    ['HP', 'Victus', '16-e', 'Ryzen 7 5800H'],
    ['HP', 'Victus', '16-r', 'i7-13th Gen'],

    // --- Latitude 5000 Series (Mainstream Business) ---
    // 14-inch Models (54xx)
    ['Dell', 'Latitude', 'E5440', 'i5-4th Gen'],
    ['Dell', 'Latitude', 'E5450', 'i5-5th Gen'],
    ['Dell', 'Latitude', '5480', 'i5-6th/7th Gen'],
    ['Dell', 'Latitude', '5490', 'i5-8th Gen'],
    ['Dell', 'Latitude', '5400', 'i5-8th Gen'],
    ['Dell', 'Latitude', '5410', 'i5-10th Gen'],
    ['Dell', 'Latitude', '5420', 'i5-11th Gen'],
    ['Dell', 'Latitude', '5430', 'i5-12th Gen'],
    ['Dell', 'Latitude', '5440', 'i5-13th Gen'],
    ['Dell', 'Latitude', '5450', 'Core Ultra 5'],
    
    // 15-inch Models (55xx)
    ['Dell', 'Latitude', 'E5540', 'i7-4th Gen'],
    ['Dell', 'Latitude', 'E5550', 'i7-5th Gen'],
    ['Dell', 'Latitude', '5580', 'i7-6th/7th Gen'],
    ['Dell', 'Latitude', '5590', 'i7-8th Gen'],
    ['Dell', 'Latitude', '5500', 'i7-8th Gen'],
    ['Dell', 'Latitude', '5510', 'i7-10th Gen'],
    ['Dell', 'Latitude', '5520', 'i7-11th Gen'],
    ['Dell', 'Latitude', '5530', 'i7-12th Gen'],
    ['Dell', 'Latitude', '5540', 'i7-13th Gen'],
    ['Dell', 'Latitude', '5550', 'Core Ultra 7'],

    // --- Latitude 7000 Series (Premium Business) ---
    // 13-inch Models (73xx)
    ['Dell', 'Latitude', '7390', 'i5-8th Gen'],
    ['Dell', 'Latitude', '7300', 'i5-8th Gen'],
    ['Dell', 'Latitude', '7310', 'i5-10th Gen'],
    ['Dell', 'Latitude', '7320', 'i5-11th Gen'],
    ['Dell', 'Latitude', '7330', 'i5-12th Gen'],
    ['Dell', 'Latitude', '7340', 'i5-13th Gen'],
    ['Dell', 'Latitude', '7350', 'Core Ultra 5'],

    // 14-inch Models (74xx)
    ['Dell', 'Latitude', 'E7440', 'i7-4th Gen'],
    ['Dell', 'Latitude', 'E7450', 'i7-5th Gen'],
    ['Dell', 'Latitude', '7480', 'i7-6th/7th Gen'],
    ['Dell', 'Latitude', '7490', 'i7-8th Gen'],
    ['Dell', 'Latitude', '7400', 'i7-8th Gen'],
    ['Dell', 'Latitude', '7410', 'i7-10th Gen'],
    ['Dell', 'Latitude', '7420', 'i7-11th Gen'],
    ['Dell', 'Latitude', '7430', 'i7-12th Gen'],
    ['Dell', 'Latitude', '7440', 'i7-13th Gen'],
    ['Dell', 'Latitude', '7450', 'Core Ultra 7'],

 // --- Latitude 9000 Series 2-in-1 (Ultra-Premium) ---
    ['Dell', 'Latitude', '9410 2-in-1', 'i7-10th Gen'],
    ['Dell', 'Latitude', '9420 2-in-1', 'i7-11th Gen'],
    ['Dell', 'Latitude', '9430 2-in-1', 'i7-12th Gen'],
    ['Dell', 'Latitude', '9440 2-in-1', 'i7-13th Gen'],
    ['Dell', 'Latitude', '9450 2-in-1', 'Core Ultra 7'],
    ['Dell', 'Latitude', '9330 2-in-1', 'i7-12th Gen'],

    // --- Latitude 7000 Series 2-in-1 (Premium) ---
    // 14-inch Models
    ['Dell', 'Latitude', '7400 2-in-1', 'i5-8th Gen'],
    ['Dell', 'Latitude', '7410 2-in-1', 'i5-10th Gen'],
    ['Dell', 'Latitude', '7420 2-in-1', 'i5-11th Gen'],
    ['Dell', 'Latitude', '7430 2-in-1', 'i5-12th Gen'],
    ['Dell', 'Latitude', '7440 2-in-1', 'i5-13th Gen'],
    ['Dell', 'Latitude', '7450 2-in-1', 'Core Ultra 5'],
    // 13-inch Models
    ['Dell', 'Latitude', '7300 2-in-1', 'i5-8th Gen'],
    ['Dell', 'Latitude', '7310 2-in-1', 'i5-10th Gen'],
    ['Dell', 'Latitude', '7320 2-in-1', 'i5-11th Gen'],
    ['Dell', 'Latitude', '7330 2-in-1', 'i5-12th Gen'],
    ['Dell', 'Latitude', '7340 2-in-1', 'i5-13th Gen'],
    ['Dell', 'Latitude', '7350 2-in-1', 'Core Ultra 5'],
    // 12-inch Models
    ['Dell', 'Latitude', '7200 2-in-1', 'i5-8th Gen'],
    ['Dell', 'Latitude', '7210 2-in-1', 'i5-10th Gen'],

    // --- Latitude 5000 Series 2-in-1 (Mainstream) ---
    ['Dell', 'Latitude', '5300 2-in-1', 'i5-8th Gen'],
    ['Dell', 'Latitude', '5310 2-in-1', 'i5-10th Gen'],
    ['Dell', 'Latitude', '5320 2-in-1', 'i5-11th Gen'],
    ['Dell', 'Latitude', '5330 2-in-1', 'i5-12th Gen'],
    ['Dell', 'Latitude', '5340 2-in-1', 'i5-13th Gen'],
    ['Dell', 'Latitude', '5350 2-in-1', 'Core Ultra 5'],

    // --- Latitude 3000 Series 2-in-1 (Essential) ---
    ['Dell', 'Latitude', '3390 2-in-1', 'i5-8th Gen'],
    ['Dell', 'Latitude', '3190 2-in-1', 'Celeron/Pentium'],
    ['Dell', 'Latitude', '3120 2-in-1', 'Celeron/Pentium'],

     // --- XPS 13 Series (Ultra-Portable) ---
    ['Dell', 'XPS', '13 9343', 'i5-5th Gen'],
    ['Dell', 'XPS', '13 9350', 'i5-6th Gen'],
    ['Dell', 'XPS', '13 9360', 'i5-7th/8th Gen'],
    ['Dell', 'XPS', '13 9370', 'i5-8th Gen'],
    ['Dell', 'XPS', '13 9380', 'i5-8th Gen'],
    ['Dell', 'XPS', '13 7390', 'i5-10th Gen'],
    ['Dell', 'XPS', '13 9300', 'i5-10th Gen'],
    ['Dell', 'XPS', '13 9310', 'i5-11th Gen'],
    ['Dell', 'XPS', '13 9315', 'i5-12th Gen'],
    ['Dell', 'XPS', '13 9320 Plus', 'i7-12th/13th Gen'],
    ['Dell', 'XPS', '13 9340', 'Core Ultra 5/7'],
    ['Dell', 'XPS', '13 9345', 'Snapdragon X Elite'],

    // --- XPS 13 2-in-1 Series (Convertible/Detachable) ---
    ['Dell', 'XPS', '13 9365 2-in-1', 'i5-7th/8th Gen (Y-series)'],
    ['Dell', 'XPS', '13 7390 2-in-1', 'i5-10th Gen'],
    ['Dell', 'XPS', '13 9310 2-in-1', 'i5-11th Gen'],
    ['Dell', 'XPS', '13 9315 2-in-1 (Detachable)', 'i5-12th Gen'],

    // --- XPS 14 Series (New 2024) ---
    ['Dell', 'XPS', '14 9440', 'Core Ultra 7'],

    // --- XPS 15 Series (Performance) ---
    ['Dell', 'XPS', '15 9550', 'i7-6th Gen'],
    ['Dell', 'XPS', '15 9560', 'i7-7th Gen'],
    ['Dell', 'XPS', '15 9570', 'i7-8th Gen'],
    ['Dell', 'XPS', '15 7590', 'i7-9th Gen'],
    ['Dell', 'XPS', '15 9500', 'i7-10th Gen'],
    ['Dell', 'XPS', '15 9510', 'i7-11th Gen'],
    ['Dell', 'XPS', '15 9520', 'i7-12th Gen'],
    ['Dell', 'XPS', '15 9530', 'i7-13th Gen'],

    // --- XPS 15 2-in-1 Series ---
    ['Dell', 'XPS', '15 9575 2-in-1', 'i7-8th Gen (Kaby Lake-G)'],

    // --- XPS 16 Series (New 2024) ---
    ['Dell', 'XPS', '16 9640', 'Core Ultra 7/9'],

    // --- XPS 17 Series (Large Screen Performance) ---
    ['Dell', 'XPS', '17 9700', 'i7-10th Gen'],
    ['Dell', 'XPS', '17 9710', 'i7-11th Gen'],
    ['Dell', 'XPS', '17 9720', 'i7-12th Gen'],
    ['Dell', 'XPS', '17 9730', 'i7-13th Gen'],

    // --- Older XPS 12 2-in-1 Series ---
    ['Dell', 'XPS', '12 9250 2-in-1', 'Core m5/m7-6th Gen'],

     // --- Precision 3000 Series (Essential Workstations) ---
    ['Dell', 'Precision', '3510', 'i7-6th Gen / Xeon E3'],
    ['Dell', 'Precision', '3520', 'i7-7th Gen / Xeon E3'],
    ['Dell', 'Precision', '3530', 'i7-8th Gen / Xeon E3'],
    ['Dell', 'Precision', '3540', 'i5-8th Gen'],
    ['Dell', 'Precision', '3541', 'i7-9th Gen'],
    ['Dell', 'Precision', '3550', 'i5-10th Gen'],
    ['Dell', 'Precision', '3551', 'i7-10th Gen'],
    ['Dell', 'Precision', '3560', 'i5-11th Gen'],
    ['Dell', 'Precision', '3561', 'i7-11th Gen'],
    ['Dell', 'Precision', '3570', 'i5-12th Gen'],
    ['Dell', 'Precision', '3571', 'i7-12th Gen'],
    ['Dell', 'Precision', '3580', 'i5-13th Gen'],
    ['Dell', 'Precision', '3581', 'i7-13th Gen'],
    ['Dell', 'Precision', '3590', 'Core Ultra 5'],
    ['Dell', 'Precision', '3591', 'Core Ultra 7'],

    // --- Precision 5000 Series (Thin & Light Workstations) ---
    ['Dell', 'Precision', '5510', 'i7-6th Gen / Xeon E3'],
    ['Dell', 'Precision', '5520', 'i7-7th Gen / Xeon E3'],
    ['Dell', 'Precision', '5530', 'i7-8th Gen / Xeon E3'],
    ['Dell', 'Precision', '5540', 'i7-9th Gen / Xeon E'],
    ['Dell', 'Precision', '5550', 'i7-10th Gen'],
    ['Dell', 'Precision', '5560', 'i7-11th Gen'],
    ['Dell', 'Precision', '5570', 'i7-12th Gen'],
    ['Dell', 'Precision', '5470', 'i7-12th Gen'],
    ['Dell', 'Precision', '5480', 'i7-13th Gen'],
    ['Dell', 'Precision', '5490', 'Core Ultra 7'],
    ['Dell', 'Precision', '5680', 'i7-13th Gen'],
    ['Dell', 'Precision', '5690', 'Core Ultra 7/9'],

    // --- Precision 7000 Series (Ultimate Workstations) ---
    // 15-inch / 16-inch Models
    ['Dell', 'Precision', '7510', 'i7-6th Gen / Xeon E3'],
    ['Dell', 'Precision', '7520', 'i7-7th Gen / Xeon E3'],
    ['Dell', 'Precision', '7530', 'i7-8th Gen / Xeon E3'],
    ['Dell', 'Precision', '7540', 'i7-9th Gen / Xeon E'],
    ['Dell', 'Precision', '7550', 'i7-10th Gen'],
    ['Dell', 'Precision', '7560', 'i7-11th Gen'],
    ['Dell', 'Precision', '7670', 'i7-12th Gen'],
    ['Dell', 'Precision', '7680', 'i7-13th Gen'],
    // 17-inch Models
    ['Dell', 'Precision', '7710', 'i7-6th Gen / Xeon E3'],
    ['Dell', 'Precision', '7720', 'i7-7th Gen / Xeon E3'],
    ['Dell', 'Precision', '7730', 'i7-8th Gen / Xeon E3'],
    ['Dell', 'Precision', '7740', 'i7-9th Gen / Xeon E'],
    ['Dell', 'Precision', '7750', 'i7-10th Gen'],
    ['Dell', 'Precision', '7760', 'i7-11th Gen'],
    ['Dell', 'Precision', '7770', 'i7-12th Gen'],
    ['Dell', 'Precision', '7780', 'i7-13th Gen'],

    // --- Inspiron 3000 Series (Essential) ---
    ['Dell', 'Inspiron', '15 3542', 'i5-4th Gen'],
    ['Dell', 'Inspiron', '15 3552', 'Celeron/Pentium'],
    ['Dell', 'Inspiron', '15 3567', 'i5-7th Gen'],
    ['Dell', 'Inspiron', '15 3583', 'i5-8th Gen'],
    ['Dell', 'Inspiron', '15 3593', 'i5-10th Gen'],
    ['Dell', 'Inspiron', '15 3501', 'i5-11th Gen'],
    ['Dell', 'Inspiron', '15 3511', 'i5-11th Gen'],
    ['Dell', 'Inspiron', '15 3520', 'i5-12th Gen'],
    ['Dell', 'Inspiron', '15 3530', 'i5-13th Gen'],
    ['Dell', 'Inspiron', '14 3493', 'i5-10th Gen'],
    ['Dell', 'Inspiron', '14 3420', 'i5-11th Gen'],

    // --- Inspiron 5000 Series (Mainstream) ---
    ['Dell', 'Inspiron', '13 5370', 'i5-8th Gen'],
    ['Dell', 'Inspiron', '13 5310', 'i5-11th Gen'],
    ['Dell', 'Inspiron', '14 5482 2-in-1', 'i5-8th Gen'],
    ['Dell', 'Inspiron', '14 5406 2-in-1', 'i5-11th Gen'],
    ['Dell', 'Inspiron', '14 5410', 'i5-11th Gen'],
    ['Dell', 'Inspiron', '14 5420', 'i5-12th Gen'],
    ['Dell', 'Inspiron', '14 5430', 'i5-13th Gen'],
    ['Dell', 'Inspiron', '14 5440', 'Core Ultra 5/7'],
    ['Dell', 'Inspiron', '14 5441', 'Snapdragon X Plus'],
    ['Dell', 'Inspiron', '15 5570', 'i7-8th Gen'],
    ['Dell', 'Inspiron', '15 5510', 'i7-11th Gen'],
    ['Dell', 'Inspiron', '15 5520', 'i7-12th Gen'],
    ['Dell', 'Inspiron', '16 5620', 'i7-12th Gen'],
    ['Dell', 'Inspiron', '16 5630', 'i7-13th Gen'],
    ['Dell', 'Inspiron', '16 5640', 'Core Ultra 7'],

    // --- Inspiron 7000 Series (Premium) ---
    ['Dell', 'Inspiron', '13 7370', 'i7-8th Gen'],
    ['Dell', 'Inspiron', '13 7391 2-in-1', 'i7-10th Gen'],
    ['Dell', 'Inspiron', '14 7490', 'i7-10th Gen'],
    ['Dell', 'Inspiron', '14 7400', 'i7-11th Gen'],
    ['Dell', 'Inspiron', '14 7420 2-in-1', 'i7-12th Gen'],
    ['Dell', 'Inspiron', '14 7430 2-in-1', 'i7-13th Gen'],
    ['Dell', 'Inspiron', '14 7440 2-in-1', 'Core Ultra 7'],
    ['Dell', 'Inspiron', '15 7570', 'i7-8th Gen'],
    ['Dell', 'Inspiron', '15 7501', 'i7-10th Gen'],
    ['Dell', 'Inspiron', '15 7510', 'i7-11th Gen'],
    ['Dell', 'Inspiron', '16 7610 Plus', 'i7-11th Gen'],
    ['Dell', 'Inspiron', '16 7620 2-in-1', 'i7-12th Gen'],
    ['Dell', 'Inspiron', '16 7630 2-in-1', 'i7-13th Gen'],
    ['Dell', 'Inspiron', '16 7640 2-in-1', 'Core Ultra 7'],
    ['Dell', 'Inspiron', '17 7706 2-in-1', 'i7-11th Gen'],

      // --- ThinkPad T Series (Mainstream Business) ---
    ['Lenovo', 'ThinkPad', 'T440', 'i5-4th Gen'],
    ['Lenovo', 'ThinkPad', 'T450', 'i5-5th Gen'],
    ['Lenovo', 'ThinkPad', 'T460', 'i5-6th Gen'],
    ['Lenovo', 'ThinkPad', 'T470', 'i5-7th Gen'],
    ['Lenovo', 'ThinkPad', 'T480', 'i5-8th Gen'],
    ['Lenovo', 'ThinkPad', 'T490', 'i5-8th/10th Gen'],
    ['Lenovo', 'ThinkPad', 'T14 Gen 1', 'i5-10th Gen / Ryzen 5 4000'],
    ['Lenovo', 'ThinkPad', 'T14 Gen 2', 'i5-11th Gen / Ryzen 5 5000'],
    ['Lenovo', 'ThinkPad', 'T14 Gen 3', 'i5-12th Gen / Ryzen 5 6000'],
    ['Lenovo', 'ThinkPad', 'T14 Gen 4', 'i5-13th Gen / Ryzen 5 7000'],
    ['Lenovo', 'ThinkPad', 'T14 Gen 5', 'Core Ultra 5 / Ryzen 5 8000'],
    ['Lenovo', 'ThinkPad', 'T14s Gen 1', 'i7-10th Gen'],
    ['Lenovo', 'ThinkPad', 'T14s Gen 4', 'i7-13th Gen'],
    ['Lenovo', 'ThinkPad', 'T16 Gen 1', 'i7-12th Gen'],
    ['Lenovo', 'ThinkPad', 'T16 Gen 2', 'i7-13th Gen'],

    // --- ThinkPad X Series (Ultra-Portable) ---
    ['Lenovo', 'ThinkPad', 'X240', 'i5-4th Gen'],
    ['Lenovo', 'ThinkPad', 'X250', 'i5-5th Gen'],
    ['Lenovo', 'ThinkPad', 'X260', 'i5-6th Gen'],
    ['Lenovo', 'ThinkPad', 'X270', 'i5-7th Gen'],
    ['Lenovo', 'ThinkPad', 'X280', 'i5-8th Gen'],
    ['Lenovo', 'ThinkPad', 'X390', 'i5-8th/10th Gen'],
    ['Lenovo', 'ThinkPad', 'X13 Gen 1', 'i5-10th Gen'],
    ['Lenovo', 'ThinkPad', 'X13 Gen 2', 'i5-11th Gen'],
    ['Lenovo', 'ThinkPad', 'X13 Gen 3', 'i5-12th Gen'],
    ['Lenovo', 'ThinkPad', 'X13 Gen 4', 'i5-13th Gen'],
    ['Lenovo', 'ThinkPad', 'X13 Gen 5', 'Core Ultra 5'],

    // --- ThinkPad X1 Series (Premium) ---
    ['Lenovo', 'ThinkPad', 'X1 Carbon Gen 1', 'i7-3rd Gen'],
    ['Lenovo', 'ThinkPad', 'X1 Carbon Gen 2', 'i7-4th Gen'],
    ['Lenovo', 'ThinkPad', 'X1 Carbon Gen 3', 'i7-5th Gen'],
    ['Lenovo', 'ThinkPad', 'X1 Carbon Gen 4', 'i7-6th Gen'],
    ['Lenovo', 'ThinkPad', 'X1 Carbon Gen 5', 'i7-7th Gen'],
    ['Lenovo', 'ThinkPad', 'X1 Carbon Gen 6', 'i7-8th Gen'],
    ['Lenovo', 'ThinkPad', 'X1 Carbon Gen 7', 'i7-8th/10th Gen'],
    ['Lenovo', 'ThinkPad', 'X1 Carbon Gen 8', 'i7-10th Gen'],
    ['Lenovo', 'ThinkPad', 'X1 Carbon Gen 9', 'i7-11th Gen'],
    ['Lenovo', 'ThinkPad', 'X1 Carbon Gen 10', 'i7-12th Gen'],
    ['Lenovo', 'ThinkPad', 'X1 Carbon Gen 11', 'i7-13th Gen'],
    ['Lenovo', 'ThinkPad', 'X1 Carbon Gen 12', 'Core Ultra 7'],
    ['Lenovo', 'ThinkPad', 'X1 Yoga Gen 1', 'i7-6th Gen'],
    ['Lenovo', 'ThinkPad', 'X1 Yoga Gen 4', 'i7-8th/10th Gen'],
    ['Lenovo', 'ThinkPad', 'X1 Yoga Gen 8', 'i7-13th Gen'],
    ['Lenovo', 'ThinkPad', 'X1 Extreme Gen 1', 'i7-8th Gen (H-series)'],
    ['Lenovo', 'ThinkPad', 'X1 Extreme Gen 2', 'i7-9th Gen (H-series)'],
    ['Lenovo', 'ThinkPad', 'X1 Extreme Gen 3', 'i7-10th Gen (H-series)'],
    ['Lenovo', 'ThinkPad', 'X1 Extreme Gen 4', 'i7-11th Gen (H-series)'],
    ['Lenovo', 'ThinkPad', 'X1 Extreme Gen 5', 'i7-12th Gen (H-series)'],

    // --- ThinkPad L Series (Value Business) ---
    ['Lenovo', 'ThinkPad', 'L440', 'i5-4th Gen'],
    ['Lenovo', 'ThinkPad', 'L450', 'i5-5th Gen'],
    ['Lenovo', 'ThinkPad', 'L460', 'i5-6th Gen'],
    ['Lenovo', 'ThinkPad', 'L470', 'i5-7th Gen'],
    ['Lenovo', 'ThinkPad', 'L480', 'i5-8th Gen'],
    ['Lenovo', 'ThinkPad', 'L490', 'i5-8th/10th Gen'],
    ['Lenovo', 'ThinkPad', 'L14 Gen 1', 'i5-10th Gen'],
    ['Lenovo', 'ThinkPad', 'L14 Gen 2', 'i5-11th Gen'],
    ['Lenovo', 'ThinkPad', 'L14 Gen 3', 'i5-12th Gen'],
    ['Lenovo', 'ThinkPad', 'L14 Gen 4', 'i5-13th Gen'],
    ['Lenovo', 'ThinkPad', 'L14 Gen 5', 'Core Ultra 5'],

    // --- ThinkPad E Series (Small Business) ---
    ['Lenovo', 'ThinkPad', 'E440', 'i5-4th Gen'],
    ['Lenovo', 'ThinkPad', 'E450', 'i5-5th Gen'],
    ['Lenovo', 'ThinkPad', 'E460', 'i5-6th Gen'],
    ['Lenovo', 'ThinkPad', 'E470', 'i5-7th Gen'],
    ['Lenovo', 'ThinkPad', 'E480', 'i5-8th Gen'],
    ['Lenovo', 'ThinkPad', 'E490', 'i5-8th/10th Gen'],
    ['Lenovo', 'ThinkPad', 'E14 Gen 1', 'i5-10th Gen'],
    ['Lenovo', 'ThinkPad', 'E14 Gen 2', 'i5-11th Gen'],
    ['Lenovo', 'ThinkPad', 'E14 Gen 3', 'Ryzen 5 5000'],
    ['Lenovo', 'ThinkPad', 'E14 Gen 4', 'i5-12th Gen'],
    ['Lenovo', 'ThinkPad', 'E14 Gen 5', 'i5-13th Gen'],
    ['Lenovo', 'ThinkPad', 'E14 Gen 6', 'Core Ultra 5'],

    // --- ThinkPad S Series (Slim/Style) ---
    ['Lenovo', 'ThinkPad', 'S440', 'i5-4th Gen'],
    ['Lenovo', 'ThinkPad', 'S540', 'i7-4th Gen'],
    ['Lenovo', 'ThinkPad', 'S1 Yoga', 'i5-4th Gen'],
    ['Lenovo', 'ThinkPad', 'S3 Yoga 14', 'i5-5th Gen'],
    ['Lenovo', 'ThinkPad', 'S5 Yoga 15', 'i7-5th Gen'],
];


    // 3. INSERT AS TEMPLATES (No S/N, Status 'In Warehouse')
    $stmt = $pdo_labels->prepare("
        INSERT INTO items (
            " . HW_FIELDS['BRAND'] . ", 
            " . HW_FIELDS['MODEL'] . ", 
            " . HW_FIELDS['SERIES'] . ", 
            " . HW_FIELDS['CPU_GEN'] . ", 
            " . HW_FIELDS['STATUS'] . "
        ) VALUES (:brand, :model, :series, :cpu_gen, 'In Warehouse')
    ");
echo" <p style=\"color:green;background-color:black;padding:10px;margin:10px;height:150px;width:300px;overflow:auto;\">";
    foreach ($seed_data as $row) {
        $stmt->execute([
            ':brand'   => $row[0],
            ':model'   => $row[1],
            ':series'  => $row[2],
            ':cpu_gen' => $row[3]
        ]);
        echo "🔹 Seeded: {$row[0]} {$row[1]} {$row[2]} ({$row[3]})\n<br />";
    }
    echo "</p>";
   echo '<div style="padding:12px; margin-bottom:10px; background:#e8f9e9; border-left:6px solid #2ecc71; font-family:Arial; font-size:14px; color:#2d572c;">
        ✅ Successfully seeded ' . count($seed_data) . ' machine templates.
      </div>';

echo '<div style="padding:12px; background:#f0f0f0; border-left:6px solid #555; font-family:Arial; font-size:14px; color:#333;">
        --- DATABASE RESET & SEED COMPLETE ---
      </div>';


} catch (PDOException $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
}
