<script setup>
import { ref, computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';

const props = defineProps({
    competition: Object,
    stages: Array,
    standings: Array,
    matches: Array,
    leaderboard: Object,
    playerStats: Array,
    awards: Array,
    filters: Object,
});

// Active tab state: 'schedule', 'standings', 'bracket', 'leaderboard', 'stats'
const activeTab = ref('schedule');

// Search & filter states for Player Stats
const statsSearch = ref('');
const activeStageType = ref(props.filters.stage_type || 'REGULAR_SEASON');

// Expand/collapse states for matches (to view games & player stats)
const expandedMatches = ref({});

const toggleMatch = (matchId) => {
    expandedMatches.value[matchId] = !expandedMatches.value[matchId];
};

// Filter matches
const regularMatches = computed(() => {
    return props.matches.filter(m => m.stage.type === 'REGULAR_SEASON');
});

const playoffMatches = computed(() => {
    return props.matches.filter(m => m.stage.type === 'PLAYOFFS');
});

// Group playoff matches by round for bracket tree view
const playoffRounds = computed(() => {
    const rounds = {
        upper: {},
        lower: {},
        grand: []
    };
    
    playoffMatches.value.forEach(m => {
        if (m.bracket_type === 'UPPER') {
            if (!rounds.upper[m.round_name]) rounds.upper[m.round_name] = [];
            rounds.upper[m.round_name].push(m);
        } else if (m.bracket_type === 'LOWER') {
            if (!rounds.lower[m.round_name]) rounds.lower[m.round_name] = [];
            rounds.lower[m.round_name].push(m);
        } else if (m.bracket_type === 'GRAND_FINAL') {
            rounds.grand.push(m);
        }
    });
    
    return rounds;
});

const sortedUpperRounds = computed(() => {
    const upper = playoffRounds.value.upper;
    const sortedKeys = Object.keys(upper).sort((a, b) => {
        const matchA = upper[a][0];
        const matchB = upper[b][0];
        return (matchA?.match_code || '').localeCompare(matchB?.match_code || '');
    });
    const result = {};
    sortedKeys.forEach(k => {
        result[k] = upper[k];
    });
    return result;
});

const sortedLowerRounds = computed(() => {
    const lower = playoffRounds.value.lower;
    const sortedKeys = Object.keys(lower).sort((a, b) => {
        const matchA = lower[a][0];
        const matchB = lower[b][0];
        return (matchA?.match_code || '').localeCompare(matchB?.match_code || '');
    });
    const result = {};
    sortedKeys.forEach(k => {
        result[k] = lower[k];
    });
    return result;
});

const unifiedColumns = computed(() => {
    const upper = playoffRounds.value.upper;
    const lower = playoffRounds.value.lower;
    const grand = playoffRounds.value.grand;
    
    const upperKeys = Object.keys(upper).sort((a, b) => {
        const matchA = upper[a][0];
        const matchB = upper[b][0];
        return (matchA?.match_code || '').localeCompare(matchB?.match_code || '');
    });
    
    const lowerKeys = Object.keys(lower).sort((a, b) => {
        const matchA = lower[a][0];
        const matchB = lower[b][0];
        return (matchA?.match_code || '').localeCompare(matchB?.match_code || '');
    });
    
    return [
        {
            name: 'UB Round 1',
            upperRound: upperKeys[0] ? { name: upperKeys[0], matches: upper[upperKeys[0]] } : null,
            lowerRound: null,
        },
        {
            name: 'UB Semifinals',
            upperRound: upperKeys[1] ? { name: upperKeys[1], matches: upper[upperKeys[1]] } : null,
            lowerRound: null,
        },
        {
            name: 'UB Final / LB Semis',
            upperRound: upperKeys[2] ? { name: upperKeys[2], matches: upper[upperKeys[2]] } : null,
            lowerRound: lowerKeys[0] ? { name: lowerKeys[0], matches: lower[lowerKeys[0]] } : null,
        },
        {
            name: 'LB Final',
            upperRound: null,
            lowerRound: lowerKeys[1] ? { name: lowerKeys[1], matches: lower[lowerKeys[1]] } : null,
        },
        {
            name: 'Grand Final',
            upperRound: null,
            lowerRound: null,
            grandFinalMatch: grand[0] || null,
        }
    ];
});

// Player stats sorting state
const sortKey = ref('avg_rating');
const sortAsc = ref(false);

const changeSort = (key) => {
    if (sortKey.value === key) {
        sortAsc.value = !sortAsc.value;
    } else {
        sortKey.value = key;
        sortAsc.value = false;
    }
};

// Filtered and sorted player stats
const filteredPlayerStats = computed(() => {
    let result = [...props.playerStats];
    
    // Search filter
    if (statsSearch.value.trim()) {
        const query = statsSearch.value.toLowerCase();
        result = result.filter(p => 
            p.name.toLowerCase().includes(query) || 
            p.team_name.toLowerCase().includes(query) ||
            p.most_played_hero.toLowerCase().includes(query)
        );
    }
    
    // Sort
    result.sort((a, b) => {
        let modifier = sortAsc.value ? 1 : -1;
        if (a[sortKey.value] < b[sortKey.value]) return -1 * modifier;
        if (a[sortKey.value] > b[sortKey.value]) return 1 * modifier;
        return 0;
    });
    
    return result;
});

// Change Stage Type filter for Player Stats Table (reloads via Inertia)
const changeStatsStage = (type) => {
    activeStageType.value = type;
    router.reload({
        only: ['playerStats'],
        data: { stage_type: type },
        preserveState: true,
    });
};

const getRoleBadgeColor = (role) => {
    const colors = {
        gold_lane: 'bg-amber-100 text-amber-800 border-amber-300',
        exp_lane: 'bg-emerald-100 text-emerald-800 border-emerald-300',
        mid_lane: 'bg-purple-100 text-purple-800 border-purple-300',
        jungle: 'bg-rose-100 text-rose-800 border-rose-300',
        roam: 'bg-sky-100 text-sky-800 border-sky-300'
    };
    return colors[role] || 'bg-slate-100 text-slate-600 border-slate-200';
};

const formatRole = (role) => {
    return role ? role.replace('_', ' ').toUpperCase() : '-';
};

const getWinnerClass = (match, teamSlot) => {
    if (!match.winner_team_id) return 'text-slate-700';
    if (teamSlot === 'A' && match.winner_team_id === match.team_a_id) return 'text-yellow-700 font-bold';
    if (teamSlot === 'B' && match.winner_team_id === match.team_b_id) return 'text-yellow-700 font-bold';
    return 'text-slate-400 line-through';
};

const getScore = (match, teamId) => {
    if (!match.games.length) return 0;
    return match.games.filter(g => g.winner_team_id === teamId).length;
};

const mvpAward = computed(() => {
    return props.awards.find(a => a.award_type === 'overall_mvp');
});

const isMobileMenuOpen = ref(false);

const tabsList = [
    { id: 'schedule', name: 'Jadwal & Hasil', icon: '📅' },
    { id: 'standings', name: 'Klasemen Liga', icon: '📈' },
    { id: 'bracket', name: 'Playoff Bracket', icon: '🌳' },
    { id: 'leaderboard', name: 'Leaderboard Lane', icon: '👑' },
    { id: 'stats', name: 'Statistik Player', icon: '📊' },
    { id: 'rules', name: 'Aturan Turnamen', icon: '📋' },
];
</script>

<template>
    <Head title="MLBB Championship" />
    <div class="min-h-screen bg-[#f8fafc] text-slate-800 font-sans selection:bg-yellow-400 selection:text-slate-950">
        
        <!-- Header -->
        <!-- Header -->
        <header class="sticky top-0 z-50 bg-white/95 backdrop-blur-md border-b border-slate-200/80 px-4 py-4 md:px-8">
            <div class="max-w-7xl mx-auto flex items-center justify-between w-full">
                <div class="flex items-center gap-3">
                    <!-- Custom SVG Crown/Cup Logo -->
                    <img src="/logo.png" alt="logo" class="w-10 h-10">
                    <div>
                        <h1 class="text-lg md:text-xl font-extrabold tracking-wider bg-gradient-to-r from-yellow-400 via-amber-300 to-yellow-500 bg-clip-text text-transparent uppercase">
                            {{ competition ? competition.name : 'MLBB AGUSTUSAN TOURNAMENT' }}
                        </h1>
                    </div>
                </div>

                <!-- Desktop Navigation Tabs -->
                <nav class="hidden md:flex items-center gap-1 bg-white p-1 rounded-xl border border-slate-200">
                    <button 
                        v-for="tab in tabsList" 
                        :key="tab.id"
                        @click="activeTab = tab.id"
                        :class="[activeTab === tab.id ? 'bg-yellow-500 text-black shadow-md' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-200/50']"
                        class="px-4 py-2 rounded-lg text-sm font-semibold tracking-wide transition-all duration-200"
                    >
                        {{ tab.name }}
                    </button>
                </nav>

                <!-- Mobile Navigation Toggle -->
                <button 
                    @click="isMobileMenuOpen = !isMobileMenuOpen" 
                    class="md:hidden flex items-center justify-center bg-slate-100 hover:bg-slate-200 border border-slate-200 rounded-xl p-2 transition text-slate-800"
                >
                    <svg v-if="!isMobileMenuOpen" xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16m-7 6h7" />
                    </svg>
                    <svg v-else xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </header>

        <!-- Mobile Menu Overlay -->
        <div v-if="isMobileMenuOpen" class="fixed inset-0 z-40 bg-slate-900/40 backdrop-blur-sm md:hidden" @click="isMobileMenuOpen = false">
            <div class="absolute top-20 right-4 left-4 bg-white border border-slate-200 rounded-3xl p-6 shadow-2xl space-y-2 animate-fadeIn" @click.stop>
                <div class="text-xs uppercase tracking-wider text-slate-400 font-bold mb-2 px-3">Navigasi Halaman</div>
                <button 
                    v-for="tab in tabsList" 
                    :key="tab.id"
                    @click="activeTab = tab.id; isMobileMenuOpen = false;"
                    :class="activeTab === tab.id ? 'bg-yellow-500 text-slate-950 font-black shadow-sm' : 'text-slate-700 hover:bg-slate-50'"
                    class="w-full text-left px-4 py-3 rounded-xl font-bold flex items-center gap-3 transition"
                >
                    <span class="text-lg">{{ tab.icon }}</span>
                    <span class="text-sm">{{ tab.name }}</span>
                </button>
            </div>
        </div>

        <!-- Official Awards Lock Banner -->
        <div v-if="awards.length > 0" class="bg-gradient-to-r from-[#1e1503] via-[#382b0f] to-[#1e1503] border-b border-yellow-700/30 text-yellow-600 py-3 px-4">
            <div class="max-w-7xl mx-auto flex items-center justify-between gap-4 text-sm font-bold">
                <div class="flex items-center gap-2">
                    <span class="text-xl">🏆</span>
                    <span>PENGHARGAAN RESMI TURNAMEN TELAH DIKUNCI! Cek pemenang di tab tab yang sesuai.</span>
                </div>
                <button @click="activeTab = 'leaderboard'" class="text-xs uppercase bg-yellow-500 hover:bg-yellow-600 text-slate-900 font-bold px-3 py-1 rounded">Lihat Pemenang</button>
            </div>
        </div>

        <main class="max-w-7xl mx-auto px-4 py-8 md:px-8">
            
            <div v-if="!competition" class="text-center py-20 bg-slate-50 border border-slate-200 rounded-3xl p-8">
                <span class="text-5xl">🎮</span>
                <h3 class="text-2xl font-extrabold mt-4 text-slate-700">Belum Ada Turnamen yang Dibuat</h3>
                <p class="text-slate-400 mt-2 max-w-md mx-auto text-sm">Panitia pelaksana belum mempublikasikan setup turnamen saat ini. Masuk ke halaman admin untuk memulai turnamen baru.</p>
                <Link href="/dashboard" class="mt-6 inline-block bg-yellow-500 hover:bg-yellow-600 text-slate-900 font-bold px-6 py-3 rounded-xl font-bold transition">Buka Admin Dashboard</Link>
            </div>

            <div v-else>
                
                <!-- Tab: Schedule & Match List -->
                <div v-if="activeTab === 'schedule'" class="space-y-8 animate-fadeIn">
                    <div class="flex items-center justify-between border-b border-slate-200 pb-4">
                        <div>
                            <h2 class="text-2xl font-black tracking-wide">JADWAL & HASIL PERTANDINGAN</h2>
                            <p class="text-sm text-slate-600 mt-1">Daftar pertandingan regular season & playoff beserta score in-game</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-6">
                        <div v-for="match in matches" :key="match.id" class="bg-white border border-slate-200/80 rounded-2xl overflow-hidden hover:border-slate-200 transition">
                            
                            <!-- Match Header info -->
                            <div class="bg-slate-50 px-6 py-4 flex flex-col sm:flex-row items-center justify-between gap-3 border-b border-slate-200/50">
                                <div class="flex items-center gap-2">
                                    <span class="bg-amber-100 text-amber-600 border border-amber-300/40 text-xs font-bold px-3 py-1 rounded-full uppercase tracking-wider">
                                        {{ match.round_name }}
                                    </span>
                                    <span v-if="match.bracket_type" class="bg-yellow-100 text-yellow-700 border border-yellow-800/40/30 text-xs font-bold px-3 py-1 rounded-full">
                                        {{ match.bracket_type }} BRACKET
                                    </span>
                                    <span class="text-slate-400 text-xs font-semibold">BO{{ match.best_of }}</span>
                                </div>
                                <div class="text-slate-600 text-xs font-bold">
                                    {{ match.scheduled_at ? new Date(match.scheduled_at).toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'short' }) : 'Belum Terjadwal' }}
                                </div>
                            </div>

                            <!-- Match Teams & Score Grid -->
                            <div class="px-6 py-8 flex flex-col md:flex-row items-center justify-between gap-8">
                                <!-- Team A -->
                                <div class="flex-1 flex items-center justify-center md:justify-end gap-4 w-full md:w-auto">
                                    <div class="text-center md:text-right">
                                        <h4 :class="getWinnerClass(match, 'A')" class="text-lg font-black tracking-wide uppercase">{{ match.team_a ? match.team_a.name : 'WAITING' }}</h4>
                                        <p class="text-xs text-slate-400 font-bold">LIGA SEED</p>
                                    </div>
                                    <div class="w-14 h-14 rounded-full bg-slate-100/50 border border-slate-200 flex items-center justify-center p-2">
                                        <img v-if="match.team_a && match.team_a.logo" :src="match.team_a.logo" class="max-h-full max-w-full object-contain" />
                                        <span v-else class="text-slate-600 text-xl">🛡️</span>
                                    </div>
                                </div>

                                <!-- Center Score & BO -->
                                <div class="flex flex-col items-center">
                                    <div class="bg-slate-50 px-6 py-2 rounded-2xl border border-slate-200/80 flex items-center gap-6 shadow-inner">
                                        <span :class="{'text-yellow-700 font-black': match.winner_team_id === match.team_a_id}" class="text-3xl font-extrabold text-slate-700">
                                            {{ match.team_a_id ? getScore(match, match.team_a_id) : '-' }}
                                        </span>
                                        <span class="text-slate-600 text-sm font-black uppercase tracking-widest">VS</span>
                                        <span :class="{'text-yellow-700 font-black': match.winner_team_id === match.team_b_id}" class="text-3xl font-extrabold text-slate-700">
                                            {{ match.team_b_id ? getScore(match, match.team_b_id) : '-' }}
                                        </span>
                                    </div>
                                    <button 
                                        @click="toggleMatch(match.id)" 
                                        v-if="match.games.length > 0"
                                        class="mt-3 text-xs font-bold text-yellow-700 hover:text-yellow-600 transition"
                                    >
                                        {{ expandedMatches[match.id] ? 'SEMBUNYIKAN DETAIL MAP' : 'LIHAT DETAIL MAP' }}
                                    </button>
                                </div>

                                <!-- Team B -->
                                <div class="flex-1 flex items-center justify-center md:justify-start gap-4 w-full md:w-auto">
                                    <div class="w-14 h-14 rounded-full bg-slate-100/50 border border-slate-200 flex items-center justify-center p-2">
                                        <img v-if="match.team_b && match.team_b.logo" :src="match.team_b.logo" class="max-h-full max-w-full object-contain" />
                                        <span v-else class="text-slate-600 text-xl">🛡️</span>
                                    </div>
                                    <div class="text-center md:text-left">
                                        <h4 :class="getWinnerClass(match, 'B')" class="text-lg font-black tracking-wide uppercase">{{ match.team_b ? match.team_b.name : 'WAITING' }}</h4>
                                        <p class="text-xs text-slate-400 font-bold">LIGA SEED</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Expandable Game & Player Stats -->
                            <div v-if="expandedMatches[match.id] && match.games.length > 0" class="border-t border-slate-200 bg-slate-50/50 px-6 py-6 space-y-6">
                                <div v-for="game in match.games" :key="game.id" class="border border-slate-200/55 rounded-xl bg-white overflow-hidden">
                                    
                                    <!-- Game Banner -->
                                    <div class="bg-slate-100 px-4 py-3 flex items-center justify-between border-b border-slate-200">
                                        <div class="text-sm font-bold text-slate-700">MAP #{{ game.game_number }}</div>
                                        <div class="text-xs text-slate-600 flex items-center gap-4">
                                            <span v-if="game.duration_seconds">⏱️ {{ Math.floor(game.duration_seconds / 60) }}m {{ game.duration_seconds % 60 }}s</span>
                                            <span class="font-extrabold text-yellow-700">Pemenang: {{ game.winner_team_id === match.team_a_id ? match.team_a.name : match.team_b.name }}</span>
                                        </div>
                                    </div>

                                    <!-- Player Game Stats table -->
                                    <div class="overflow-x-auto">
                                        <table class="w-full text-left text-xs border-collapse">
                                            <thead>
                                                <tr class="border-b border-slate-200 text-slate-600 bg-slate-900/30">
                                                    <th class="p-3">Player</th>
                                                    <th class="p-3">Hero</th>
                                                    <th class="p-3">K/D/A</th>
                                                    <th class="p-3">Gold</th>
                                                    <th class="p-3">Rating</th>
                                                    <th class="p-3 text-center">MVP</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr 
                                                    v-for="stat in game.player_stats || []" 
                                                    :key="stat.id" 
                                                    :class="{'bg-yellow-950/20': stat.is_mvp}"
                                                    class="border-b border-slate-200/40 hover:bg-slate-100"
                                                >
                                                    <td class="p-3 font-bold">
                                                        <div class="flex flex-col">
                                                            <span>{{ stat.player.name }}</span>
                                                            <span class="text-[10px] text-slate-400 uppercase">{{ formatRole(stat.player.role) }}</span>
                                                        </div>
                                                    </td>
                                                    <td class="p-3 font-semibold text-slate-700">{{ stat.hero }}</td>
                                                    <td class="p-3 font-mono text-slate-800">
                                                        <span class="text-green-600 font-bold">{{ stat.kills }}</span>/
                                                        <span class="text-red-600 font-bold">{{ stat.deaths }}</span>/
                                                        <span class="text-blue-400 font-bold">{{ stat.assists }}</span>
                                                    </td>
                                                    <td class="p-3 text-yellow-600 font-semibold">{{ stat.gold_earned.toLocaleString('id-ID') }}</td>
                                                    <td class="p-3 font-extrabold text-yellow-700">{{ stat.rating }}</td>
                                                    <td class="p-3 text-center">
                                                        <span v-if="stat.is_mvp" class="bg-yellow-500 text-black px-2 py-0.5 rounded font-black text-[10px] uppercase shadow-md shadow-none">MVP</span>
                                                        <span v-else class="text-slate-700">-</span>
                                                    </td>
                                                </tr>
                                                <!-- Fallback if playerStats not loaded -->
                                                <tr v-if="!game.player_stats || game.player_stats.length === 0">
                                                    <td colspan="6" class="p-4 text-center text-slate-600">Tidak ada detail statistik player untuk game ini.</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>

                                </div>
                            </div>

                        </div>
                    </div>
                </div>

                <!-- Tab: Standings Liga -->
                <div v-if="activeTab === 'standings'" class="space-y-6 animate-fadeIn">
                    <div class="flex items-center justify-between border-b border-slate-200 pb-4">
                        <div>
                            <h2 class="text-2xl font-black tracking-wide">KLASEMEN LIGA REGULAR SEASON</h2>
                            <p class="text-sm text-slate-600 mt-1">Klasemen klasifikasi liga berdasarkan format tiebreak resmi</p>
                        </div>
                    </div>

                    <div class="bg-white border border-slate-200 rounded-3xl overflow-hidden shadow-2xl">
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse">
                                <thead>
                                    <tr class="border-b border-slate-200 bg-slate-100 text-slate-600 font-extrabold text-xs tracking-wider">
                                        <th class="p-5 text-center w-16">Rank</th>
                                        <th class="p-5">Tim</th>
                                        <th class="p-5 text-center">Played</th>
                                        <th class="p-5 text-center">Wins</th>
                                        <th class="p-5 text-center">Losses</th>
                                        <th class="p-5 text-center">Games W/L</th>
                                        <th class="p-5 text-center">Net Games</th>
                                        <th class="p-5 text-center">Match Points</th>
                                        <th class="p-5">Status Kelolosan</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr 
                                        v-for="row in standings" 
                                        :key="row.team_id" 
                                        :class="[
                                            row.rank <= 2 ? 'bg-yellow-100/10 hover:bg-yellow-100/20' : '',
                                            row.rank > 2 && row.rank <= 6 ? 'bg-amber-100/5 hover:bg-amber-100/15' : '',
                                            row.rank > 6 ? 'hover:bg-slate-100' : ''
                                        ]"
                                        class="border-b border-slate-200/50 transition"
                                    >
                                        <td class="p-5 text-center font-black text-lg">
                                            <span 
                                                :class="[
                                                    row.rank === 1 ? 'text-yellow-700' : '',
                                                    row.rank === 2 ? 'text-slate-700' : '',
                                                    row.rank === 3 ? 'text-amber-600' : '',
                                                    row.rank > 3 ? 'text-slate-400' : ''
                                                ]"
                                            >
                                                #{{ row.rank }}
                                            </span>
                                        </td>
                                        <td class="p-5">
                                            <div class="flex items-center gap-3">
                                                <div class="w-10 h-10 rounded-full bg-slate-100 p-1 flex items-center justify-center border border-slate-200/50">
                                                    <img v-if="row.logo" :src="row.logo" class="max-h-full max-w-full object-contain" />
                                                    <span v-else>🛡️</span>
                                                </div>
                                                <span class="font-black text-slate-800 tracking-wide uppercase">{{ row.name }}</span>
                                            </div>
                                        </td>
                                        <td class="p-5 text-center font-bold text-slate-700">{{ row.played }}</td>
                                        <td class="p-5 text-center text-green-600 font-bold">{{ row.wins }}</td>
                                        <td class="p-5 text-center text-red-600 font-bold">{{ row.losses }}</td>
                                        <td class="p-5 text-center font-mono text-slate-600">{{ row.games_won }} - {{ row.games_lost }}</td>
                                        <td class="p-5 text-center font-bold" :class="row.net_games >= 0 ? 'text-yellow-700' : 'text-red-600'">
                                            {{ row.net_games > 0 ? '+' : '' }}{{ row.net_games }}
                                        </td>
                                        <td class="p-5 text-center font-black text-slate-800 text-lg">{{ row.points }}</td>
                                        <td class="p-5">
                                            <span v-if="row.rank <= 2" class="bg-yellow-100/60 border border-yellow-500/30 text-yellow-600 text-[10px] font-black uppercase px-3 py-1 rounded-full">
                                                Bye Upper Bracket Semis
                                            </span>
                                            <span v-else-if="row.rank <= 6" class="bg-amber-100 border border-amber-200 text-amber-600 text-[10px] font-black uppercase px-3 py-1 rounded-full">
                                                Lolos UB Round 1
                                            </span>
                                            <span v-else class="bg-slate-100 border border-slate-200 text-slate-500 text-[10px] font-bold uppercase px-3 py-1 rounded-full">
                                                Eliminated
                                            </span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Tab: Playoff Bracket Tree View -->
                <div v-if="activeTab === 'bracket'" class="space-y-8 animate-fadeIn">
                    <div class="flex items-center justify-between border-b border-slate-200 pb-4">
                        <div>
                            <h2 class="text-2xl font-black tracking-wide">DOUBLE ELIMINATION PLAYOFF BRACKET</h2>
                            <p class="text-sm text-slate-600 mt-1">Bagan Upper Bracket, Lower Bracket, dan Grand Final hasil generate otomatis</p>
                        </div>
                    </div>

                    <div v-if="playoffMatches.length === 0" class="text-center py-16 bg-white border border-slate-200 rounded-3xl p-8">
                        <span class="text-5xl">📊</span>
                        <h3 class="text-xl font-bold mt-4">Bracket Belum Di-generate</h3>
                        <p class="text-slate-400 mt-2 max-w-sm mx-auto text-sm">Playoff baru akan dimulai setelah Regular Season liga diselesaikan dan admin mengunci bracket playoff.</p>
                    </div>

                    <div v-else>
                        <!-- NEW BRACKET LAYOUT (5-column fixed pixel grid):
                             Col0=UB R1 | Col1=UB Semis+LB Semis | Col2=UB Final | Col3=LB Final | Col4=GF
                             CARD POSITIONS:
                               Col0 left=10:   UB_R1_M2 top=40 cY=85  | UB_R1_M1 top=190 cY=235
                               Col1 left=280:  UB_R2_M2 top=40 cY=85  | UB_R2_M1 top=190 cY=235 | LB_R1_M1 top=415 cY=460
                               Col2 left=550:  UB_R3_M1 top=115 cY=160
                               Col3 left=820:  LB_R2_M1 top=415 cY=460
                               Col4 left=1090: GF top=250
                        -->
                        <div class="bracket-grid-container overflow-x-auto pb-6">
                            <div class="bracket-grid" style="width:1060px; height:560px;">

                                <!-- SVG OVERLAY: all connecting lines drawn first (behind cards) -->
                                <svg class="bracket-svg" xmlns="http://www.w3.org/2000/svg" style="z-index:1">

                                    <!-- ══ Col0 → Col1: UB R1 winners → UB Semis (straight horizontal) ══ -->
                                    <line x1="190" y1="85"  x2="225" y2="85"  stroke="#94a3b8" stroke-width="2"/>
                                    <line x1="190" y1="235" x2="225" y2="235" stroke="#94a3b8" stroke-width="2"/>

                                    <!-- ══ UB Semis winners → UB Final (merge bracket) ══
                                         Top card (85) and bottom card (235) right side x=405,
                                         both go to midpoint x=422, converge at y=160, enter UB Final left x=440 -->
                                    <path d="M 405 85  H 422 V 160 H 440" stroke="#94a3b8" stroke-width="2" fill="none"/>
                                    <path d="M 405 235 H 422 V 160"        stroke="#94a3b8" stroke-width="2" fill="none"/>

                                    <!-- ══ UB Semis LOSERS → LB Semis (STRAIGHT VERTICAL DOWN, dashed red) ══
                                         Both losers in Col1. LB Semis card is also in Col1.
                                         Exit from bottom of UB Semis cards (y=130 and y=280) going straight down
                                         to top of LB Semis card (y=415). Both use center-x of col1 = 315.
                                         Offset by ±15px to show two separate loser paths. -->
                                    <line x1="300" y1="130" x2="300" y2="415" stroke="#f87171" stroke-width="1.5" stroke-dasharray="5,3" opacity="0.8"/>
                                    <line x1="330" y1="280" x2="330" y2="415" stroke="#f87171" stroke-width="1.5" stroke-dasharray="5,3" opacity="0.8"/>

                                    <!-- ══ UB Final winner → GF top slot (GOLDEN) ══
                                         UB Final cY=160 → right to x=852 → down to GF team-A row y=275 → left into GF x=870 -->
                                    <path d="M 620 160 H 852 V 275 H 870" stroke="#eab308" stroke-width="2.5" fill="none"/>

                                    <!-- ══ UB Final LOSER → LB Final (dashed red) ══
                                         UB Final cY=160 → right to gap x=637 → drop to LB Final cY=460 → left into LB Final x=655 -->
                                    <path d="M 620 160 H 637 V 460 H 655" stroke="#f87171" stroke-width="1.5" stroke-dasharray="5,3" fill="none" opacity="0.8"/>

                                    <!-- ══ LB Semis winner → LB Final (horizontal, passes below UB Final card safely) ══
                                         LB Semis right=405, cY=460 → straight right → LB Final left=655, cY=460 -->
                                    <line x1="405" y1="460" x2="655" y2="460" stroke="#94a3b8" stroke-width="2"/>

                                    <!-- ══ LB Final winner → GF bottom slot (gray) ══
                                         LB Final right=835, cY=460 → right to x=852 → up to GF team-B row y=320 → left into GF x=870 -->
                                    <path d="M 835 460 H 852 V 320 H 870" stroke="#94a3b8" stroke-width="2" fill="none"/>

                                </svg>

                                <!-- ════ COL 0: UB ROUND 1 ════ -->
                                <div class="bracket-col" style="left:10px">
                                    <div class="bracket-col-label text-yellow-700">UPPER BRACKET ROUND 1</div>
                                    <!-- UB_R1_M2: top=40, cY=85 -->
                                    <div v-if="playoffRounds.upper['Upper Bracket Round 1']" class="bracket-match-card" style="top:40px">
                                        <template v-for="m in playoffRounds.upper['Upper Bracket Round 1'].filter(x => x.match_code === 'UB_R1_M2')" :key="m.id">
                                            <div class="card-header">
                                                <span class="code-tag yellow">{{ m.match_code }}</span>
                                                <span class="bo-tag">BO{{ m.best_of }}</span>
                                            </div>
                                            <div class="card-body">
                                                <div class="team-row">
                                                    <span :class="getWinnerClass(m,'A')">{{ m.team_a ? m.team_a.name : 'TBD' }}</span>
                                                    <span :class="{'score-win': m.winner_team_id === m.team_a_id}">{{ m.team_a_id ? getScore(m,m.team_a_id) : 0 }}</span>
                                                </div>
                                                <div class="team-row">
                                                    <span :class="getWinnerClass(m,'B')">{{ m.team_b ? m.team_b.name : 'TBD' }}</span>
                                                    <span :class="{'score-win': m.winner_team_id === m.team_b_id}">{{ m.team_b_id ? getScore(m,m.team_b_id) : 0 }}</span>
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                    <!-- UB_R1_M1: top=190, cY=235 -->
                                    <div v-if="playoffRounds.upper['Upper Bracket Round 1']" class="bracket-match-card" style="top:190px">
                                        <template v-for="m in playoffRounds.upper['Upper Bracket Round 1'].filter(x => x.match_code === 'UB_R1_M1')" :key="m.id">
                                            <div class="card-header">
                                                <span class="code-tag yellow">{{ m.match_code }}</span>
                                                <span class="bo-tag">BO{{ m.best_of }}</span>
                                            </div>
                                            <div class="card-body">
                                                <div class="team-row">
                                                    <span :class="getWinnerClass(m,'A')">{{ m.team_a ? m.team_a.name : 'TBD' }}</span>
                                                    <span :class="{'score-win': m.winner_team_id === m.team_a_id}">{{ m.team_a_id ? getScore(m,m.team_a_id) : 0 }}</span>
                                                </div>
                                                <div class="team-row">
                                                    <span :class="getWinnerClass(m,'B')">{{ m.team_b ? m.team_b.name : 'TBD' }}</span>
                                                    <span :class="{'score-win': m.winner_team_id === m.team_b_id}">{{ m.team_b_id ? getScore(m,m.team_b_id) : 0 }}</span>
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                </div>

                                <!-- ════ COL 1: UB SEMIFINALS (top) + LB SEMIFINALS (bottom) ════ -->
                                <div class="bracket-col" style="left:225px">
                                    <!-- UB Semis label at very top -->
                                    <div class="bracket-col-label text-yellow-700">UB SEMIFINALS</div>

                                    <!-- UB_R2_M2: top=40, cY=85 — loser drops from y=130 -->
                                    <div v-if="playoffRounds.upper['Upper Bracket Semifinals']" class="bracket-match-card" style="top:40px">
                                        <template v-for="m in playoffRounds.upper['Upper Bracket Semifinals'].filter(x => x.match_code === 'UB_R2_M2')" :key="m.id">
                                            <div class="card-header">
                                                <span class="code-tag yellow">{{ m.match_code }}</span>
                                                <span class="bo-tag">BO{{ m.best_of }}</span>
                                            </div>
                                            <div class="card-body">
                                                <div class="team-row">
                                                    <span :class="getWinnerClass(m,'A')">{{ m.team_a ? m.team_a.name : 'TBD' }}</span>
                                                    <span :class="{'score-win': m.winner_team_id === m.team_a_id}">{{ m.team_a_id ? getScore(m,m.team_a_id) : 0 }}</span>
                                                </div>
                                                <div class="team-row">
                                                    <span :class="getWinnerClass(m,'B')">{{ m.team_b ? m.team_b.name : 'TBD' }}</span>
                                                    <span :class="{'score-win': m.winner_team_id === m.team_b_id}">{{ m.team_b_id ? getScore(m,m.team_b_id) : 0 }}</span>
                                                </div>
                                            </div>
                                        </template>
                                    </div>

                                    <!-- UB_R2_M1: top=190, cY=235 — loser drops from y=280 -->
                                    <div v-if="playoffRounds.upper['Upper Bracket Semifinals']" class="bracket-match-card" style="top:190px">
                                        <template v-for="m in playoffRounds.upper['Upper Bracket Semifinals'].filter(x => x.match_code === 'UB_R2_M1')" :key="m.id">
                                            <div class="card-header">
                                                <span class="code-tag yellow">{{ m.match_code }}</span>
                                                <span class="bo-tag">BO{{ m.best_of }}</span>
                                            </div>
                                            <div class="card-body">
                                                <div class="team-row">
                                                    <span :class="getWinnerClass(m,'A')">{{ m.team_a ? m.team_a.name : 'TBD' }}</span>
                                                    <span :class="{'score-win': m.winner_team_id === m.team_a_id}">{{ m.team_a_id ? getScore(m,m.team_a_id) : 0 }}</span>
                                                </div>
                                                <div class="team-row">
                                                    <span :class="getWinnerClass(m,'B')">{{ m.team_b ? m.team_b.name : 'TBD' }}</span>
                                                    <span :class="{'score-win': m.winner_team_id === m.team_b_id}">{{ m.team_b_id ? getScore(m,m.team_b_id) : 0 }}</span>
                                                </div>
                                            </div>
                                        </template>
                                    </div>

                                    <!-- LB Semis label — appears above LB Semis card -->
                                    <div style="position:absolute; top:385px; left:0; width:100%; text-align:center; font-size:9px; font-weight:900; text-transform:uppercase; letter-spacing:0.08em; color:#dc2626;">LB SEMIFINALS</div>

                                    <!-- LB_R1_M1: top=415, cY=460 -->
                                    <div v-if="playoffRounds.lower['Lower Bracket Semifinals']" class="bracket-match-card" style="top:415px">
                                        <template v-for="m in playoffRounds.lower['Lower Bracket Semifinals']" :key="m.id">
                                            <div class="card-header">
                                                <span class="code-tag red">{{ m.match_code }}</span>
                                                <span class="bo-tag">BO{{ m.best_of }}</span>
                                            </div>
                                            <div class="card-body">
                                                <div class="team-row">
                                                    <span :class="getWinnerClass(m,'A')">{{ m.team_a ? m.team_a.name : 'TBD' }}</span>
                                                    <span :class="{'score-win': m.winner_team_id === m.team_a_id}">{{ m.team_a_id ? getScore(m,m.team_a_id) : 0 }}</span>
                                                </div>
                                                <div class="team-row">
                                                    <span :class="getWinnerClass(m,'B')">{{ m.team_b ? m.team_b.name : 'TBD' }}</span>
                                                    <span :class="{'score-win': m.winner_team_id === m.team_b_id}">{{ m.team_b_id ? getScore(m,m.team_b_id) : 0 }}</span>
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                </div>

                                <!-- ════ COL 2: UB FINAL only ════ -->
                                <div class="bracket-col" style="left:440px">
                                    <div class="bracket-col-label text-yellow-700">UB FINAL</div>
                                    <!-- UB_R3_M1: top=115, cY=160 (midpoint of cY=85 and cY=235) -->
                                    <div v-if="playoffRounds.upper['Upper Bracket Final']" class="bracket-match-card" style="top:115px">
                                        <template v-for="m in playoffRounds.upper['Upper Bracket Final']" :key="m.id">
                                            <div class="card-header">
                                                <span class="code-tag yellow">{{ m.match_code }}</span>
                                                <span class="bo-tag">BO{{ m.best_of }}</span>
                                            </div>
                                            <div class="card-body">
                                                <div class="team-row">
                                                    <span :class="getWinnerClass(m,'A')">{{ m.team_a ? m.team_a.name : 'TBD' }}</span>
                                                    <span :class="{'score-win': m.winner_team_id === m.team_a_id}">{{ m.team_a_id ? getScore(m,m.team_a_id) : 0 }}</span>
                                                </div>
                                                <div class="team-row">
                                                    <span :class="getWinnerClass(m,'B')">{{ m.team_b ? m.team_b.name : 'TBD' }}</span>
                                                    <span :class="{'score-win': m.winner_team_id === m.team_b_id}">{{ m.team_b_id ? getScore(m,m.team_b_id) : 0 }}</span>
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                </div>

                                <!-- ════ COL 3: LB FINAL ════ -->
                                <div class="bracket-col" style="left:655px">
                                    <!-- LB Final label at bottom area -->
                                    <div style="position:absolute; top:385px; left:0; width:100%; text-align:center; font-size:9px; font-weight:900; text-transform:uppercase; letter-spacing:0.08em; color:#dc2626;">LB FINAL</div>
                                    <!-- LB_R2_M1: top=415, cY=460 — same height as LB Semis -->
                                    <div v-if="playoffRounds.lower['Lower Bracket Final']" class="bracket-match-card" style="top:415px">
                                        <template v-for="m in playoffRounds.lower['Lower Bracket Final']" :key="m.id">
                                            <div class="card-header">
                                                <span class="code-tag red">{{ m.match_code }}</span>
                                                <span class="bo-tag">BO{{ m.best_of }}</span>
                                            </div>
                                            <div class="card-body">
                                                <div class="team-row">
                                                    <span :class="getWinnerClass(m,'A')">{{ m.team_a ? m.team_a.name : 'TBD' }}</span>
                                                    <span :class="{'score-win': m.winner_team_id === m.team_a_id}">{{ m.team_a_id ? getScore(m,m.team_a_id) : 0 }}</span>
                                                </div>
                                                <div class="team-row">
                                                    <span :class="getWinnerClass(m,'B')">{{ m.team_b ? m.team_b.name : 'TBD' }}</span>
                                                    <span :class="{'score-win': m.winner_team_id === m.team_b_id}">{{ m.team_b_id ? getScore(m,m.team_b_id) : 0 }}</span>
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                </div>

                                <!-- ════ COL 4: GRAND FINAL ════ -->
                                <div class="bracket-col" style="left:870px">
                                    <div class="bracket-col-label text-yellow-700">👑 GRAND FINAL</div>
                                    <!-- GF: top=250, team-A row cY≈275, team-B row cY≈320 -->
                                    <div v-if="playoffRounds.grand[0]" class="bracket-match-card gf-card" style="top:250px">
                                        <div class="card-header gf-header">
                                            <span class="text-[9px] font-black tracking-wider uppercase">{{ playoffRounds.grand[0].round_name }}</span>
                                            <span class="text-[8px] bg-black text-white px-1.5 py-0.5 rounded font-bold">BO{{ playoffRounds.grand[0].best_of }}</span>
                                        </div>
                                        <div class="card-body">
                                            <div class="team-row">
                                                <span class="font-black uppercase text-[10px]" :class="getWinnerClass(playoffRounds.grand[0],'A')">
                                                    {{ playoffRounds.grand[0].team_a ? playoffRounds.grand[0].team_a.name : 'UB WINNER' }}
                                                </span>
                                                <span :class="{'score-win': playoffRounds.grand[0].winner_team_id === playoffRounds.grand[0].team_a_id}">
                                                    {{ playoffRounds.grand[0].team_a_id ? getScore(playoffRounds.grand[0], playoffRounds.grand[0].team_a_id) : 0 }}
                                                </span>
                                            </div>
                                            <div class="team-row">
                                                <span class="font-black uppercase text-[10px]" :class="getWinnerClass(playoffRounds.grand[0],'B')">
                                                    {{ playoffRounds.grand[0].team_b ? playoffRounds.grand[0].team_b.name : 'LB WINNER' }}
                                                </span>
                                                <span :class="{'score-win': playoffRounds.grand[0].winner_team_id === playoffRounds.grand[0].team_b_id}">
                                                    {{ playoffRounds.grand[0].team_b_id ? getScore(playoffRounds.grand[0], playoffRounds.grand[0].team_b_id) : 0 }}
                                                </span>
                                            </div>
                                            <div v-if="playoffRounds.grand[0].winner_team_id" class="mt-2 text-center text-[9px] font-black text-yellow-700 uppercase bg-yellow-500/10 border border-yellow-400/30 rounded-lg py-1">
                                                🏆 CHAMPION: {{ playoffRounds.grand[0].winner_team.name }}
                                            </div>
                                        </div>
                                    </div>
                                </div>

                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tab: Leaderboard Lane (Best Players per Lane) -->
                <div v-if="activeTab === 'leaderboard'" class="space-y-8 animate-fadeIn">
                    
                    <!-- Tournament Awards Showcase (If Locked) -->
                    <div v-if="awards.length > 0" class="space-y-6">
                        <div class="border-b border-yellow-800 pb-3">
                            <h2 class="text-2xl font-black tracking-wide text-yellow-700 uppercase">🏆 HASIL RESMI TOURNAMENT AWARDS</h2>
                            <p class="text-xs text-slate-600 mt-1">Hasil penghargaan akhir kompetisi berdasarkan statistik rata-rata performa rating</p>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <!-- Show MVP First -->
                            <div v-if="mvpAward" class="md:col-span-3 bg-gradient-to-r from-yellow-600/30 via-amber-500/10 to-yellow-600/30 border border-yellow-500/60 p-6 rounded-3xl text-center shadow-lg relative overflow-hidden">
                                <div class="absolute -right-4 -bottom-4 text-8xl opacity-10">👑</div>
                                <span class="bg-yellow-500 text-black px-4 py-1 rounded-full text-xs font-black tracking-widest uppercase">OVERALL MVP</span>
                                <h3 class="text-3xl font-black mt-3 text-yellow-600 tracking-wider">
                                    {{ mvpAward.player.name }}
                                </h3>
                                <p class="text-slate-600 font-bold text-sm mt-1 uppercase">{{ mvpAward.player.team.name }}</p>
                                <div class="mt-4 bg-yellow-500/10 border border-yellow-500/30 rounded-xl px-4 py-2 inline-flex items-center gap-2">
                                    <span class="text-xs text-slate-600 font-bold uppercase">RATING RATA-RATA:</span>
                                    <span class="font-extrabold text-yellow-700 text-lg">{{ mvpAward.avg_rating }}</span>
                                </div>
                            </div>

                            <!-- Lane Awards -->
                            <div 
                                v-for="award in awards.filter(a => a.award_type !== 'overall_mvp')" 
                                :key="award.id"
                                class="bg-white border border-yellow-500/20 p-5 rounded-2xl shadow-md text-center"
                            >
                                <span class="text-xs font-black uppercase text-yellow-600 tracking-wider">
                                    {{ award.award_type.replace('best_', 'BEST ').toUpperCase() }}
                                </span>
                                <h4 class="text-lg font-black text-slate-800 mt-2 tracking-wide uppercase">{{ award.player.name }}</h4>
                                <p class="text-xs text-slate-600 font-semibold uppercase mt-0.5">{{ award.player.team.name }}</p>
                                <div class="mt-3 text-xs text-slate-700">
                                    Rata-rata Rating: <span class="text-yellow-700 font-black text-sm">{{ award.avg_rating }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Current Leaderboard (Running) -->
                    <div class="space-y-6">
                        <div class="flex items-center justify-between border-b border-slate-200 pb-4">
                            <div>
                                <h2 class="text-2xl font-black tracking-wide">LEADERBOARD PER LANE (BERJALAN)</h2>
                                <p class="text-sm text-slate-600 mt-1">Daftar pemain dengan rata-rata rating performa tertinggi pada masing-masing role</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-5 gap-6">
                            <div v-for="(players, lane) in leaderboard" :key="lane" class="bg-white border border-slate-200 rounded-2xl overflow-hidden p-4">
                                <div class="text-center pb-3 border-b border-slate-200 mb-4">
                                    <span :class="getRoleBadgeColor(lane)" class="border px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-wider">
                                        {{ lane.replace('_lane', '') }}
                                    </span>
                                </div>

                                <div v-if="players.length === 0" class="text-center py-6 text-slate-400 text-xs font-semibold">Belum ada data game.</div>
                                
                                <div v-else class="space-y-4">
                                    <!-- Top 1 Player -->
                                    <div class="bg-gradient-to-b from-yellow-50 to-amber-50 border border-slate-200/50 rounded-xl p-3 text-center shadow-inner relative overflow-hidden">
                                        <span class="absolute top-1 left-2 text-yellow-600 font-black text-[10px]">#1</span>
                                        <div class="w-8 h-8 rounded-full bg-slate-100 p-0.5 mx-auto mb-2 flex items-center justify-center border border-slate-200">
                                            <img v-if="players[0].team_logo" :src="players[0].team_logo" class="max-h-full max-w-full object-contain" />
                                            <span v-else>🛡️</span>
                                        </div>
                                        <h4 class="text-sm font-black text-slate-800 truncate uppercase">{{ players[0].name.split(' ').pop() }}</h4>
                                        <p class="text-[9px] text-slate-600 font-bold uppercase truncate">{{ players[0].team_name }}</p>
                                        <div class="text-yellow-700 font-black text-sm mt-1.5">★ {{ players[0].avg_rating }}</div>
                                    </div>

                                    <!-- Top 2 & 3 Players -->
                                    <div class="space-y-2 text-xs">
                                        <div v-for="(p, idx) in players.slice(1, 3)" :key="p.player_id" class="flex items-center justify-between bg-slate-50 p-2 rounded-lg border border-slate-200/60">
                                            <div class="flex items-center gap-1.5 truncate">
                                                <span class="text-slate-400 font-bold">#{{ idx + 2 }}</span>
                                                <span class="font-bold text-slate-700 truncate uppercase">{{ p.name.split(' ').pop() }}</span>
                                            </div>
                                            <span class="font-bold text-yellow-700">{{ p.avg_rating }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>

                <!-- Tab: Statistik Pemain -->
                <div v-if="activeTab === 'stats'" class="space-y-6 animate-fadeIn">
                    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 border-b border-slate-200 pb-4">
                        <div>
                            <h2 class="text-2xl font-black tracking-wide">STATISTIK SEMUA PEMAIN</h2>
                            <p class="text-sm text-slate-600 mt-1">Leaderboard statistik KDA, Rating, Gold, & Hero terpopuler</p>
                        </div>

                        <!-- Stage Toggle Filter -->
                        <div class="flex bg-slate-100 border border-slate-200 p-1 rounded-xl">
                            <button 
                                @click="changeStatsStage('REGULAR_SEASON')"
                                :class="activeStageType === 'REGULAR_SEASON' ? 'bg-yellow-500 text-slate-950 font-black shadow-sm' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-200/50'"
                                class="px-4 py-1.5 text-xs rounded-lg transition"
                            >
                                LIGA REGULER
                            </button>
                            <button 
                                @click="changeStatsStage('PLAYOFFS')"
                                :class="activeStageType === 'PLAYOFFS' ? 'bg-yellow-500 text-slate-950 font-black shadow-sm' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-200/50'"
                                class="px-4 py-1.5 text-xs rounded-lg transition"
                            >
                                PLAYOFFS
                            </button>
                        </div>
                    </div>

                    <!-- Search Bar -->
                    <div class="flex items-center bg-white border border-slate-200 rounded-2xl px-4 py-3 max-w-md">
                        <span class="text-slate-400 text-lg mr-2">🔍</span>
                        <input 
                            v-model="statsSearch" 
                            type="text" 
                            placeholder="Cari player, tim, atau hero favorit..." 
                            class="bg-transparent border-none text-slate-800 text-sm focus:ring-0 w-full placeholder-slate-600 outline-none"
                        />
                    </div>

                    <!-- Stats Table -->
                    <div class="bg-white border border-slate-200 rounded-3xl overflow-hidden shadow-2xl">
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse text-sm">
                                <thead>
                                    <tr class="border-b border-slate-200 bg-slate-100 text-slate-600 font-extrabold text-xs uppercase tracking-wider">
                                        <th class="p-4 cursor-pointer hover:text-slate-900" @click="changeSort('name')">Pemain</th>
                                        <th class="p-4 cursor-pointer hover:text-slate-900" @click="changeSort('team_name')">Tim</th>
                                        <th class="p-4 cursor-pointer hover:text-slate-900" @click="changeSort('role')">Role</th>
                                        <th class="p-4 text-center cursor-pointer hover:text-slate-900" @click="changeSort('games_played')">Games</th>
                                        <th class="p-4 text-center cursor-pointer hover:text-slate-900" @click="changeSort('avg_kda')">Avg KDA</th>
                                        <th class="p-4 text-center cursor-pointer hover:text-slate-900" @click="changeSort('avg_gold')">Avg Gold</th>
                                        <th class="p-4 text-center cursor-pointer hover:text-slate-900" @click="changeSort('most_played_hero')">Hero Fav</th>
                                        <th class="p-4 text-center cursor-pointer hover:text-slate-900" @click="changeSort('mvp_count')">MVP</th>
                                        <th class="p-4 text-center cursor-pointer hover:text-slate-900" @click="changeSort('avg_rating')">Avg Rating</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr 
                                        v-for="p in filteredPlayerStats" 
                                        :key="p.player_id"
                                        class="border-b border-slate-200/40 hover:bg-slate-100 transition"
                                    >
                                        <td class="p-4 font-black text-slate-800 uppercase tracking-wide">{{ p.name }}</td>
                                        <td class="p-4 font-bold text-slate-600 uppercase">{{ p.team_name }}</td>
                                        <td class="p-4">
                                            <span :class="getRoleBadgeColor(p.role)" class="border px-2.5 py-0.5 rounded text-[9px] font-black uppercase tracking-wider">
                                                {{ p.role.replace('_lane', '') }}
                                            </span>
                                        </td>
                                        <td class="p-4 text-center font-bold text-slate-700">{{ p.games_played }}</td>
                                        <td class="p-4 text-center font-mono">
                                            <div class="flex flex-col">
                                                <span class="font-extrabold text-slate-800">{{ p.avg_kda }}</span>
                                                <span class="text-[10px] text-slate-400 font-bold">({{ p.avg_kills }}/{{ p.avg_deaths }}/{{ p.avg_assists }})</span>
                                            </div>
                                        </td>
                                        <td class="p-4 text-center text-yellow-600 font-bold font-mono">{{ p.avg_gold.toLocaleString('id-ID') }}</td>
                                        <td class="p-4 text-center font-semibold text-slate-700">{{ p.most_played_hero }}</td>
                                        <td class="p-4 text-center">
                                            <span v-if="p.mvp_count > 0" class="bg-yellow-500/10 border border-yellow-500/30 text-yellow-700 text-xs font-bold px-2 py-0.5 rounded-full">
                                                {{ p.mvp_count }} MVP
                                            </span>
                                            <span v-else class="text-slate-600">-</span>
                                        </td>
                                        <td class="p-4 text-center font-black text-yellow-700 text-base">{{ p.avg_rating }}</td>
                                    </tr>
                                    <tr v-if="filteredPlayerStats.length === 0">
                                        <td colspan="9" class="p-8 text-center text-slate-600">Tidak menemukan player dengan pencarian tersebut.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Tab: Rules of the Games -->
                <div v-if="activeTab === 'rules'" class="space-y-8 animate-fadeIn">
                    <div class="border-b border-slate-200 pb-4">
                        <h2 class="text-2xl font-black tracking-wide">📋 ATURAN TURNAMEN</h2>
                        <p class="text-sm text-slate-600 mt-1">Panduan lengkap format kompetisi, peraturan umum, dan pelaporan hasil tanding</p>
                    </div>

                    <!-- Welcome Banner -->
                    <div class="bg-gradient-to-r from-yellow-500/10 via-amber-500/5 to-yellow-500/10 border border-yellow-500/30 rounded-3xl p-6 md:p-8 flex flex-col md:flex-row items-center justify-between gap-6">
                        <div class="space-y-2">
                            <h3 class="text-xl font-black text-yellow-700 uppercase">🇮🇩 HUT RI ke-81 Kabayan Group</h3>
                            <p class="text-sm text-slate-700 font-medium">Halo Sobat Kabayan! 👋 Berikut adalah aturan turnamen Mobile Legends HUT RI ke-81 Kabayan Group.</p>
                        </div>
                        <div class="text-4xl">🏆</div>
                    </div>

                    <!-- Grid Layout for Tournament Format -->
                    <div class="space-y-6">
                        <h3 class="text-lg font-black uppercase tracking-wider text-slate-700 border-l-4 border-yellow-500 pl-3">📌 Format Turnamen</h3>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <!-- Card 1: Regular Season -->
                            <div class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm space-y-4">
                                <div class="flex items-center gap-3">
                                    <span class="text-2xl bg-amber-50 p-2 rounded-xl text-amber-600">🎮</span>
                                    <h4 class="font-extrabold text-slate-800 uppercase tracking-wide">1. Regular Season (Online)</h4>
                                </div>
                                <ul class="text-xs text-slate-600 space-y-2.5 font-medium list-disc list-inside">
                                    <li>Seluruh pertandingan dilaksanakan secara <strong>online</strong>.</li>
                                    <li>Menggunakan sistem <strong>Half Round Robin</strong>.</li>
                                    <li>Setiap tim akan bertemu <strong>1 kali</strong> dengan seluruh tim lainnya.</li>
                                    <li>Setiap pertandingan menggunakan format <strong>Best of 3 (BO3)</strong>.</li>
                                    <li>Peringkat klasemen menentukan posisi pada babak Playoff.</li>
                                </ul>
                            </div>

                            <!-- Card 2: Playoff -->
                            <div class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm space-y-4">
                                <div class="flex items-center gap-3">
                                    <span class="text-2xl bg-amber-50 p-2 rounded-xl text-amber-600">🏆</span>
                                    <h4 class="font-extrabold text-slate-800 uppercase tracking-wide">2. Playoff (Offline)</h4>
                                </div>
                                <ul class="text-xs text-slate-600 space-y-2.5 font-medium list-disc list-inside">
                                    <li>Menggunakan sistem <strong>Double Elimination Bracket</strong>.</li>
                                    <li>Seluruh pertandingan menggunakan format <strong>Best of 3 (BO3)</strong>.</li>
                                    <li>Tim yang kalah di Upper Bracket masih memiliki kesempatan di Lower Bracket.</li>
                                    <li>Tim yang kalah dua kali akan tereliminasi dari turnamen.</li>
                                </ul>
                            </div>

                            <!-- Card 3: Grand Final -->
                            <div class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm space-y-4">
                                <div class="flex items-center gap-3">
                                    <span class="text-2xl bg-amber-50 p-2 rounded-xl text-amber-600">👑</span>
                                    <h4 class="font-extrabold text-slate-800 uppercase tracking-wide">3. Grand Final (Offline)</h4>
                                </div>
                                <ul class="text-xs text-slate-600 space-y-2.5 font-medium list-disc list-inside">
                                    <li>Menggunakan format <strong>Best of 5 (BO5)</strong>.</li>
                                    <li>Pemenang Upper Bracket akan bertemu pemenang Lower Bracket.</li>
                                    <li>Tim yang memenangkan <strong>3 game</strong> terlebih dahulu dinyatakan sebagai Juara Turnamen.</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <!-- General Rules & Screenshot Reporting -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Card 4: General Rules -->
                        <div class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm space-y-4">
                            <div class="flex items-center gap-3 border-b border-slate-100 pb-3">
                                <span class="text-2xl">📋</span>
                                <h4 class="font-extrabold text-slate-800 uppercase tracking-wide">Aturan Umum</h4>
                            </div>
                            <ul class="text-xs text-slate-600 space-y-2.5 font-medium list-inside list-none">
                                <li class="flex items-start gap-2"><span>🛡️</span> <span>Mode pertandingan menggunakan <strong>Custom Tournament Draft Pick (5v5)</strong>.</span></li>
                                <li class="flex items-start gap-2"><span>👥</span> <span>Setiap tim terdiri dari <strong>5 pemain</strong>.</span></li>
                                <li class="flex items-start gap-2"><span>🔑</span> <span>Seluruh peserta wajib menggunakan akun milik sendiri.</span></li>
                                <li class="flex items-start gap-2"><span>🔄</span> <span>Pergantian pemain hanya diperbolehkan sebelum pertandingan dimulai dan harus diinformasikan kepada panitia.</span></li>
                                <li class="flex items-start gap-2"><span>⏰</span> <span>Seluruh peserta wajib hadir sesuai jadwal yang telah ditentukan.</span></li>
                                <li class="flex items-start gap-2"><span>🚨</span> <span>Keterlambatan lebih dari <strong>10 menit</strong> tanpa konfirmasi akan dianggap <strong>Walk Over (WO)</strong>.</span></li>
                                <li class="flex items-start gap-2"><span>🚫</span> <span>Dilarang menggunakan cheat, script, map hack, bug abuse, maupun aplikasi pihak ketiga.</span></li>
                                <li class="flex items-start gap-2"><span>🤐</span> <span>Dilarang melakukan tindakan tidak sportif seperti toxic chat atau menghina lawan.</span></li>
                                <li class="flex items-start gap-2"><span>⚖️</span> <span>Keputusan panitia bersifat <strong>mutlak dan tidak dapat diganggu gugat</strong>.</span></li>
                            </ul>
                        </div>

                        <!-- Card 5: Screenshot Reporting -->
                        <div class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm flex flex-col justify-between gap-6">
                            <div class="space-y-4">
                                <div class="flex items-center gap-3 border-b border-slate-100 pb-3">
                                    <span class="text-2xl">📸</span>
                                    <h4 class="font-extrabold text-slate-800 uppercase tracking-wide">Pelaporan Hasil Pertandingan</h4>
                                </div>
                                <ul class="text-xs text-slate-600 space-y-2.5 font-medium list-disc list-inside">
                                    <li>Tim pemenang wajib mengirimkan <strong>Screenshot (SS) hasil pertandingan</strong> kepada panitia.</li>
                                    <li>Screenshot wajib menampilkan hasil akhir <strong>(Victory)</strong>.</li>
                                    <li>Hasil pertandingan baru dianggap sah setelah dicatat oleh panitia.</li>
                                    <li>Apabila terjadi perselisihan hasil pertandingan, panitia berhak meminta bukti tambahan.</li>
                                </ul>
                            </div>

                            <!-- Motivational Slogan -->
                            <div class="bg-gradient-to-r from-red-600 via-orange-500 to-yellow-500 border border-red-500/20 text-white p-5 rounded-xl text-center shadow-md">
                                <span class="block text-xs font-black tracking-widest uppercase">SLOGAN KOMPETISI</span>
                                <h4 class="text-lg font-black tracking-wider mt-1.5 uppercase">🔥 MENANG ITU BONUS, KOMPAK ITU UTAMA!</h4>
                                <p class="text-[10px] text-red-50 mt-1 uppercase font-semibold">Selamat bertanding dan semoga menjadi Juara Mobile Legends HUT RI ke-81 Kabayan Group! 🇮🇩</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </main>
        
        <footer class="border-t border-slate-200/80 bg-[#f8fafc] py-8 text-center text-xs text-slate-400 font-semibold tracking-wider">
            <div class="max-w-7xl mx-auto px-4 space-y-2">
                <p>© 2026 PANITIA TURNAMEN MOBILE LEGENDS INDONESIA. ALL RIGHTS RESERVED.</p>
                <p class="text-[10px] text-slate-600">Designed with dark-futuristic aesthetic inspired by MPL ID.</p>
            </div>
        </footer>

    </div>
</template>

<style scoped>
.animate-fadeIn {
    animation: fadeIn 0.4s ease forwards;
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(5px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* ===== NEW: Fixed-Height Bracket Grid with SVG Overlay ===== */

/* Outer scrollable container */
.bracket-grid-container {
    width: 100%;
}

/* The actual fixed-size grid canvas */
.bracket-grid {
    position: relative;
    width: 1060px;    /* 5 cols × 180px + 4 gaps × 35px = 1060px */
    height: 560px;
    min-height: 560px;
    margin: 0 auto;
}

/* SVG overlay that sits behind all cards */
.bracket-svg {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    z-index: 1;
    overflow: visible;
}

/* Each column is 180px wide, absolutely placed */
.bracket-col {
    position: absolute;
    width: 180px;
    height: 560px;
}

/* Column header label at top */
.bracket-col-label {
    position: absolute;
    top: 40px;
    left: 0;
    width: 100%;
    text-align: center;
    font-size: 9px;
    font-weight: 900;
    text-transform: uppercase;
    letter-spacing: 0.08em;
}

/* Column label at the BOTTOM half */
.bracket-col-label-bottom {
    position: absolute;
    top: 375px;
    left: 0;
    width: 100%;
    text-align: center;
    font-size: 9px;
    font-weight: 900;
    text-transform: uppercase;
    letter-spacing: 0.08em;
}

/* Individual match card – absolutely positioned within its column */
.bracket-match-card {
    position: absolute;
    left: 0;
    width: 180px;
    z-index: 10;
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0,0,0,0.07);
    transition: box-shadow 0.2s;
}
.bracket-match-card:hover {
    box-shadow: 0 4px 16px rgba(0,0,0,0.12);
}

/* Grand Final card special styling */
.bracket-match-card.gf-card {
    border-color: #eab308;
    box-shadow: 0 4px 20px rgba(234,179,8,0.15);
}

/* Card inner elements */
.card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 5px 10px;
    background: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
}

.card-header.gf-header {
    background: linear-gradient(to right, #eab308, #f59e0b);
    color: black;
}

.card-body {
    padding: 8px 10px;
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.team-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-size: 11px;
    font-weight: 700;
    color: #334155;
}

.code-tag {
    font-size: 9px;
    font-weight: 900;
    text-transform: uppercase;
}
.code-tag.yellow { color: #b45309; }
.code-tag.red    { color: #dc2626; }

.bo-tag {
    font-size: 9px;
    font-weight: 700;
    color: #64748b;
}

.score-win {
    color: #b45309;
    font-weight: 900;
}

/* Winner/loser text color helpers (used by getWinnerClass) */
.text-winner { color: #b45309; font-weight: 900; }
.text-loser  { color: #94a3b8; }

</style>
