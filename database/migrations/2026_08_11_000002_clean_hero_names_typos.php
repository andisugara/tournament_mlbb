<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\PlayerGameStat;

return new class extends Migration
{
    public function up(): void
    {
        $masterHeroes = [
            "Miya", "Balmond", "Saber", "Alice", "Nana", "Tigreal", "Alucard", "Karina", "Akai", "Franco",
            "Bane", "Bruno", "Clint", "Rafaela", "Eudora", "Zilong", "Fanny", "Layla", "Minotaur", "Lolita",
            "Hayabusa", "Freya", "Gord", "Natalia", "Kagura", "Chou", "Sun", "Alpha", "Ruby", "Yi Sun-shin",
            "Moskov", "Johnson", "Cyclops", "Estes", "Hilda", "Aurora", "Lapu-Lapu", "Vexana", "Roger", "Karrie",
            "Gatotkaca", "Harley", "Irithel", "Grock", "Argus", "Odette", "Lancelot", "Diggie", "Hylos", "Zhask",
            "Helcurt", "Pharsa", "Lesley", "Jawhead", "Angela", "Gusion", "Valir", "Martis", "Uranus", "Hanabi",
            "Chang'e", "Kaja", "Selena", "Aldous", "Claude", "Belerick", "Lunox", "Thamuz", "Leomord", "Kimmy",
            "Harith", "Minsitthar", "Hanzo", "Kadita", "Badang", "Faramis", "Vale", "Guinevere", "Khufra", "Esmeralda",
            "Granger", "Terizla", "Dyrroth", "Lylia", "X.Borg", "Masha", "Baxia", "Ling", "Wanwan", "Silvanna",
            "Carmilla", "Cecilion", "Atlas", "Popol and Kupa", "Luo Yi", "Yu Zhong", "Khaleed", "Barats", "Brody", "Benedetta",
            "Mathilda", "Paquito", "Yve", "Beatrix", "Gloo", "Phoveus", "Natan", "Aulus", "Floryn", "Aamon",
            "Valentina", "Edith", "Yin", "Melissa", "Xavier", "Julian", "Fredrinn", "Joy", "Novaria", "Arlott",
            "Ixia", "Nolan", "Cici", "Zhuxin", "Chip", "Suyou", "Lukas", "Kalea", "Zetian", "Obsidia",
            "Sora", "Marcel", "Hirara"
        ];

        $abbreviations = [
            'yss' => 'Yi Sun-shin',
            'yi sun shin' => 'Yi Sun-shin',
            'yi shun shin' => 'Yi Sun-shin',
            'popol' => 'Popol and Kupa',
            'popol & kupa' => 'Popol and Kupa',
            'popol kupa' => 'Popol and Kupa',
            'popol and kupa' => 'Popol and Kupa',
            'x.borg' => 'X.Borg',
            'xborg' => 'X.Borg',
            'x-borg' => 'X.Borg',
            'xb' => 'X.Borg',
            'x borg' => 'X.Borg',
            'esme' => 'Esmeralda',
            'paq' => 'Paquito',
            'yz' => 'Yu Zhong',
            'yu zhong' => 'Yu Zhong',
            'yuzhong' => 'Yu Zhong',
            'lapu' => 'Lapu-Lapu',
            'lapulapu' => 'Lapu-Lapu',
            'lapu-lapu' => 'Lapu-Lapu',
            'lapu lapu' => 'Lapu-Lapu',
            'gatot' => 'Gatotkaca',
            'gatotkaca' => 'Gatotkaca',
            'gatot kaca' => 'Gatotkaca',
            'minsi' => 'Minsitthar',
            'minsithar' => 'Minsitthar',
            'minsitar' => 'Minsitthar',
            'minshithar' => 'Minsitthar',
            'alu' => 'Alucard',
            'gus' => 'Gusion',
            'haya' => 'Hayabusa',
            'lance' => 'Lancelot',
            'change' => "Chang'e",
            'chang e' => "Chang'e",
            'carmila' => 'Carmilla',
            'guinivere' => 'Guinevere',
            'fredrin' => 'Fredrinn',
            'minotour' => 'Minotaur',
            'rubby' => 'Ruby',
            'dyroth' => 'Dyrroth',
            'karie' => 'Karrie',
            'kufra' => 'Khufra',
            'arlot' => 'Arlott',
            'marchel' => 'Marcel',
            'tamuz' => 'Thamuz',
            'suoyou' => 'Suyou',
            'suyo' => 'Suyou',
            'wan wan' => 'Wanwan',
            'silvana' => 'Silvanna',
            'odete' => 'Odette',
            'obisidia' => 'Obsidia',
            'hanabo' => 'Hanabi',
            'sylvana' => 'Silvanna',
            'jhonson' => 'Johnson',
            'khaled' => 'Khaleed',
            'khalled' => 'Khaleed',
            'nathan' => 'Natan',
        ];

        // Create lowercase lookup maps
        $heroLookup = [];
        foreach ($masterHeroes as $h) {
            $heroLookup[strtolower($h)] = $h;
        }

        $allStats = PlayerGameStat::all();
        foreach ($allStats as $stat) {
            $normalized = strtolower(trim($stat->hero));
            $corrected = null;

            if (isset($abbreviations[$normalized])) {
                $corrected = $abbreviations[$normalized];
            } elseif (isset($heroLookup[$normalized])) {
                $corrected = $heroLookup[$normalized];
            }

            if ($corrected && $corrected !== $stat->hero) {
                $stat->hero = $corrected;
                $stat->save();
            }
        }
    }

    public function down(): void
    {
        // No-op: Cleaning typos doesn't need to be rolled back
    }
};
