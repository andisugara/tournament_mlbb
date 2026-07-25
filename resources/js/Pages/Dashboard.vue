<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';

const props = defineProps({
    competition: Object,
    stages: Array,
    teams: Array,
    players: Array,
    matches: Array,
    standings: Array,
    awards: Array,
});

// Setup Form
const setupForm = useForm({
    name: '',
    total_teams: 8,
    teams_advance_to_playoff: 6,
    upper_bracket_direct_seed: 2,
    regular_season_best_of: 3,
    playoff_upper_best_of: 3,
    playoff_lower_best_of: 3,
    playoff_gf_best_of: 7,
    is_double_round_robin: false,
});

// Setup Form Validations/Warnings (Frontend)
const setupWarnings = computed(() => {
    const warnings = [];
    const T = setupForm.teams_advance_to_playoff;
    const D = setupForm.upper_bracket_direct_seed;
    const total = setupForm.total_teams;

    if (T > total) {
        warnings.push('⚠️ Jumlah tim playoff tidak boleh melebihi jumlah total tim.');
    }
    if ((T - D) % 2 !== 0) {
        warnings.push('⚠️ Selisih (Tim Playoff - Seed Langsung) harus genap untuk pembentukan bracket.');
    }
    
    // Check standard configurations
    const isValidConfig = (T === 4 && D === 0) || (T === 6 && D === 2) || (T === 8 && D === 0);
    if (!isValidConfig) {
        warnings.push('⚠️ Format yang didukung: 4 tim (0 seed langsung), 6 tim (2 seed langsung), atau 8 tim (0 seed langsung).');
    }

    return warnings;
});

const submitSetup = () => {
    setupForm.post(route('admin.setup'), {
        onSuccess: () => setupForm.reset(),
    });
};

// CRUD Teams
const teamForm = useForm({ name: '' });
const submitTeam = () => {
    teamForm.post(route('admin.teams.store'), {
        onSuccess: () => teamForm.reset(),
    });
};
const deleteTeam = (id) => {
    if (confirm('Hapus tim ini? Semua pemain di tim ini juga akan terhapus.')) {
        router.delete(route('admin.teams.destroy', id));
    }
};

// CRUD Players
const playerForm = useForm({
    team_id: '',
    name: '',
    role: 'gold_lane',
});
const submitPlayer = () => {
    playerForm.post(route('admin.players.store'), {
        onSuccess: () => playerForm.reset('name'),
    });
};
const deletePlayer = (id) => {
    if (confirm('Hapus pemain ini?')) {
        router.delete(route('admin.players.destroy', id));
    }
};

// Generate Playoffs
const generatePlayoffs = () => {
    if (confirm('Apakah Anda yakin ingin mengunci klasemen liga dan men-generate playoff bracket? Ini akan me-reset bracket jika sudah ada.')) {
        router.post(route('admin.generate-playoffs'));
    }
};

// Lock Awards
const lockAwards = () => {
    if (confirm('Hitung dan kunci penghargaan resmi turnamen berdasarkan rating rata-rata?')) {
        router.post(route('admin.lock-awards'));
    }
};

// Reset Tournament
const resetForm = useForm({
    delete_teams: false,
});
const isResetModalOpen = ref(false);

const submitReset = () => {
    resetForm.post(route('admin.reset'), {
        onSuccess: () => {
            isResetModalOpen.value = false;
            resetForm.reset();
        }
    });
};

// Edit Match Form & Modal State
const selectedEditMatch = ref(null);
const editMatchForm = useForm({
    id: null,
    team_a_id: '',
    team_b_id: '',
    scheduled_at: '',
    round_name: '',
    best_of: 3,
});

const openEditMatchModal = (match) => {
    selectedEditMatch.value = match;
    editMatchForm.id = match.id;
    editMatchForm.team_a_id = match.team_a_id || '';
    editMatchForm.team_b_id = match.team_b_id || '';
    
    if (match.scheduled_at) {
        const d = new Date(match.scheduled_at);
        const offset = d.getTimezoneOffset() * 60000;
        const localISOTime = (new Date(d.getTime() - offset)).toISOString().slice(0, 16);
        editMatchForm.scheduled_at = localISOTime;
    } else {
        editMatchForm.scheduled_at = '';
    }
    
    editMatchForm.round_name = match.round_name || '';
    editMatchForm.best_of = match.best_of || 3;
};

const submitEditMatch = () => {
    editMatchForm.patch(route('admin.matches.update', editMatchForm.id), {
        onSuccess: () => {
            selectedEditMatch.value = null;
            editMatchForm.reset();
        },
        onError: (err) => {
            alert('Gagal mengupdate pertandingan: ' + Object.values(err).join(', '));
        }
    });
};

// Edit Team Form & Modal State
const selectedEditTeam = ref(null);
const editTeamForm = useForm({
    id: null,
    name: '',
    logo: '',
});

const openEditTeamModal = (team) => {
    selectedEditTeam.value = team;
    editTeamForm.id = team.id;
    editTeamForm.name = team.name || '';
    editTeamForm.logo = team.logo || '';
};

const submitEditTeam = () => {
    editTeamForm.patch(route('admin.teams.update', editTeamForm.id), {
        onSuccess: () => {
            selectedEditTeam.value = null;
            editTeamForm.reset();
        },
        onError: (err) => {
            alert('Gagal mengupdate tim: ' + Object.values(err).join(', '));
        }
    });
};

// Edit Player Form & Modal State
const selectedEditPlayer = ref(null);
const editPlayerForm = useForm({
    id: null,
    team_id: '',
    name: '',
    role: 'gold_lane',
});

const openEditPlayerModal = (player) => {
    selectedEditPlayer.value = player;
    editPlayerForm.id = player.id;
    editPlayerForm.team_id = player.team_id || '';
    editPlayerForm.name = player.name || '';
    editPlayerForm.role = player.role || 'gold_lane';
};

const submitEditPlayer = () => {
    editPlayerForm.patch(route('admin.players.update', editPlayerForm.id), {
        onSuccess: () => {
            selectedEditPlayer.value = null;
            editPlayerForm.reset();
        },
        onError: (err) => {
            alert('Gagal mengupdate pemain: ' + Object.values(err).join(', '));
        }
    });
};

// Game Score Input Modal State
const selectedMatch = ref(null);
const activeGameNumber = ref(1);
const gameWinnerId = ref('');
const gameDuration = ref(900);
const playerStatsInput = ref([]);

const openScoreModal = (match) => {
    selectedMatch.value = match;
    activeGameNumber.value = match.games.length + 1;
    if (activeGameNumber.value > match.best_of) {
        activeGameNumber.value = 1; // reset/edit option
    }
    gameWinnerId.value = '';
    gameDuration.value = 900;
    
    // Initialize stats inputs for the 10 players of team_a and team_b
    const teamAPlayers = props.players.filter(p => p.team_id === match.team_a_id);
    const teamBPlayers = props.players.filter(p => p.team_id === match.team_b_id);
    const allMatchPlayers = [...teamAPlayers, ...teamBPlayers];

    playerStatsInput.value = allMatchPlayers.map(p => {
        // Try to pre-load stats if editing an existing game
        return {
            player_id: p.id,
            name: p.name,
            role: p.role,
            team_id: p.team_id,
            hero: '',
            kills: 0,
            deaths: 0,
            assists: 0,
            gold_earned: 10000,
            rating: 6.0,
            is_mvp: false
        };
    });
};

const handleMvpCheck = (index) => {
    // Force single selection for MVP
    playerStatsInput.value.forEach((player, idx) => {
        if (idx !== index) {
            player.is_mvp = false;
        }
    });
};

const scoreForm = useForm({
    game_number: 1,
    winner_team_id: '',
    duration_seconds: 900,
    player_stats: [],
});

const submitGameScore = () => {
    if (!gameWinnerId.value) {
        alert('Mohon pilih tim pemenang map.');
        return;
    }

    // Validate MVP checked
    const mvpCount = playerStatsInput.value.filter(p => p.is_mvp).length;
    if (mvpCount === 0) {
        alert('Mohon tandai 1 pemain sebagai MVP map.');
        return;
    }

    scoreForm.game_number = activeGameNumber.value;
    scoreForm.winner_team_id = parseInt(gameWinnerId.value);
    scoreForm.duration_seconds = gameDuration.value;
    scoreForm.player_stats = playerStatsInput.value;

    scoreForm.post(route('admin.game.store', selectedMatch.value.id), {
        onSuccess: () => {
            selectedMatch.value = null;
            scoreForm.reset();
        },
        onError: (err) => {
            alert('Gagal menyimpan score: ' + Object.values(err).join(', '));
        }
    });
};

// Admin tabs
const adminTab = ref('matches');
</script>

<template>
    <Head title="Panitia Dashboard" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-bold leading-tight text-slate-800 uppercase tracking-wide">
                KABAYAN TOURNAMENT PANEL
            </h2>
        </template>

        <div class="py-12 bg-[#f8fafc] min-h-screen text-slate-800">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-8">
                
                <!-- If NO turnamen exists -->
                <div v-if="!competition" class="bg-white border border-slate-200 rounded-3xl p-8 max-w-2xl mx-auto shadow-xl">
                    <h3 class="text-2xl font-black tracking-wide text-yellow-700 uppercase">Buat Turnamen Baru</h3>
                    <p class="text-sm text-slate-600 mt-2">Isi konfigurasi di bawah untuk meng-generate tahapan liga dan playoff otomatis.</p>
                    
                    <form @submit.prevent="submitSetup" class="mt-6 space-y-5">
                        <div>
                            <label class="block text-xs uppercase tracking-wider text-slate-600 font-bold mb-2">Nama Turnamen</label>
                            <input v-model="setupForm.name" type="text" placeholder="Contoh: Piala Kemerdekaan 2026" class="w-full bg-white border border-slate-200 rounded-xl px-4 py-3 text-sm focus:border-yellow-500 focus:ring-0 text-slate-800" required />
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-xs uppercase tracking-wider text-slate-600 font-bold mb-2">Jumlah Tim Liga</label>
                                <input v-model="setupForm.total_teams" type="number" min="4" class="w-full bg-white border border-slate-200 rounded-xl px-4 py-3 text-sm text-slate-800" required />
                            </div>
                            <div>
                                <label class="block text-xs uppercase tracking-wider text-slate-600 font-bold mb-2">Lolos Playoff</label>
                                <input v-model="setupForm.teams_advance_to_playoff" type="number" min="4" class="w-full bg-white border border-slate-200 rounded-xl px-4 py-3 text-sm text-slate-800" required />
                            </div>
                            <div>
                                <label class="block text-xs uppercase tracking-wider text-slate-600 font-bold mb-2">UB Direct Seeds (Bye)</label>
                                <input v-model="setupForm.upper_bracket_direct_seed" type="number" min="0" class="w-full bg-white border border-slate-200 rounded-xl px-4 py-3 text-sm text-slate-800" required />
                            </div>
                        </div>

                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                            <div>
                                <label class="block text-xs uppercase tracking-wider text-slate-600 font-bold mb-2">Liga BO</label>
                                <select v-model="setupForm.regular_season_best_of" class="w-full bg-white border border-slate-200 rounded-xl px-4 py-3 text-sm text-slate-800" required>
                                    <option :value="1">BO1</option>
                                    <option :value="3">BO3</option>
                                    <option :value="5">BO5</option>
                                    <option :value="7">BO7</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs uppercase tracking-wider text-slate-600 font-bold mb-2">UB Playoff BO</label>
                                <select v-model="setupForm.playoff_upper_best_of" class="w-full bg-white border border-slate-200 rounded-xl px-4 py-3 text-sm text-slate-800" required>
                                    <option :value="1">BO1</option>
                                    <option :value="3">BO3</option>
                                    <option :value="5">BO5</option>
                                    <option :value="7">BO7</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs uppercase tracking-wider text-slate-600 font-bold mb-2">LB Playoff BO</label>
                                <select v-model="setupForm.playoff_lower_best_of" class="w-full bg-white border border-slate-200 rounded-xl px-4 py-3 text-sm text-slate-800" required>
                                    <option :value="1">BO1</option>
                                    <option :value="3">BO3</option>
                                    <option :value="5">BO5</option>
                                    <option :value="7">BO7</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs uppercase tracking-wider text-slate-600 font-bold mb-2">GF Playoff BO</label>
                                <select v-model="setupForm.playoff_gf_best_of" class="w-full bg-white border border-slate-200 rounded-xl px-4 py-3 text-sm text-slate-800" required>
                                    <option :value="1">BO1</option>
                                    <option :value="3">BO3</option>
                                    <option :value="5">BO5</option>
                                    <option :value="7">BO7</option>
                                </select>
                            </div>
                        </div>

                        <div class="mt-4">
                            <label class="flex items-center gap-2.5 cursor-pointer bg-white/40 border border-slate-200/80 p-3.5 rounded-xl hover:bg-white/60 transition">
                                <input 
                                    type="checkbox" 
                                    v-model="setupForm.is_double_round_robin" 
                                    class="rounded border-slate-200 bg-slate-900 text-yellow-600 focus:ring-0 w-4 h-4"
                                />
                                <div>
                                    <span class="text-xs font-bold text-slate-800 block uppercase">Liga Kandang & Tandang (Double Round Robin)</span>
                                    <span class="text-[10px] text-slate-600 block mt-0.5">Tandai ini untuk menghasilkan jadwal dua putaran pertemuan bolak-balik bagi semua tim liga.</span>
                                </div>
                            </label>
                        </div>

                        <!-- Frontend Warnings -->
                        <div v-if="setupWarnings.length > 0" class="bg-yellow-500/10 border border-yellow-500/20 rounded-2xl p-4 text-xs text-yellow-700 space-y-1">
                            <div v-for="warning in setupWarnings" :key="warning">{{ warning }}</div>
                        </div>

                        <div class="text-right pt-4">
                            <button 
                                type="submit" 
                                :disabled="setupWarnings.length > 0 || setupForm.processing"
                                class="bg-yellow-500 hover:bg-yellow-400 disabled:opacity-50 text-black px-6 py-3 rounded-xl font-extrabold text-sm uppercase tracking-wider transition shadow-md shadow-none"
                            >
                                Generate Turnamen & Jadwal
                            </button>
                        </div>
                    </form>
                </div>

                <!-- If Turnamen exists -->
                <div v-else class="space-y-8">
                    <!-- Competition Status Header Card -->
                    <div class="bg-gradient-to-r from-slate-100 to-slate-200 border border-slate-200 rounded-3xl p-6 shadow-xl flex flex-col md:flex-row justify-between items-center gap-6">
                        <div>
                            <span class="bg-yellow-100 border border-yellow-800/40 text-yellow-700 text-[10px] font-black uppercase px-3 py-1 rounded-full tracking-widest">TURNAMEN AKTIF</span>
                            <h3 class="text-2xl font-black text-slate-800 tracking-wide mt-2 uppercase">{{ competition.name }}</h3>
                            <div class="flex flex-col gap-1.5 mt-3 text-xs text-slate-600">
                                <div class="flex items-center gap-6">
                                    <span>Liga: <strong>{{ competition.total_teams }} Tim</strong></span>
                                    <span>Lolos Knockout: <strong>{{ competition.teams_advance_to_playoff }} Tim</strong></span>
                                    <span>Direct Seeds: <strong>{{ competition.upper_bracket_direct_seed }} Tim (Bye)</strong></span>
                                </div>
                                <div>
                                    Format BO: Liga <strong>BO{{ competition.regular_season_best_of }}</strong> | Playoff Upper <strong>BO{{ competition.playoff_upper_best_of }}</strong> | Playoff Lower <strong>BO{{ competition.playoff_lower_best_of }}</strong> | Grand Final <strong>BO{{ competition.playoff_gf_best_of }}</strong>
                                </div>
                            </div>
                        </div>
                        <div class="flex flex-wrap items-center gap-3">
                            <button @click="generatePlayoffs" class="bg-amber-500 hover:bg-amber-600 text-white font-bold px-4 py-2.5 rounded-xl text-xs font-black uppercase tracking-wider transition">
                                ⚡ Generate Playoff
                            </button>
                            <button @click="lockAwards" class="bg-yellow-500 hover:bg-yellow-600 text-slate-900 font-bold px-4 py-2.5 rounded-xl text-xs font-black uppercase tracking-wider transition">
                                🔒 Kunci Awards
                            </button>
                            <button @click="isResetModalOpen = true" class="bg-rose-600 hover:bg-rose-500 text-white px-4 py-2.5 rounded-xl text-xs font-black uppercase tracking-wider transition">
                                🔴 Reset Turnamen
                            </button>
                        </div>
                    </div>

                    <!-- Admin Tabs Navigation -->
                    <div class="flex items-center gap-2 border-b border-slate-200/80 pb-3">
                        <button @click="adminTab = 'matches'" :class="adminTab === 'matches' ? 'border-yellow-500 text-yellow-700' : 'border-transparent text-slate-600 hover:text-white'" class="border-b-2 px-4 py-2 font-bold text-sm transition">Jadwal & Input Score</button>
                        <button @click="adminTab = 'standings'" :class="adminTab === 'standings' ? 'border-yellow-500 text-yellow-700' : 'border-transparent text-slate-600 hover:text-white'" class="border-b-2 px-4 py-2 font-bold text-sm transition">Klasemen & Award</button>
                        <button @click="adminTab = 'teams'" :class="adminTab === 'teams' ? 'border-yellow-500 text-yellow-700' : 'border-transparent text-slate-600 hover:text-white'" class="border-b-2 px-4 py-2 font-bold text-sm transition">Manage Teams & Players</button>
                    </div>

                    <!-- Tab content: Matches -->
                    <div v-if="adminTab === 'matches'" class="space-y-6">
                        <div class="grid grid-cols-1 gap-4">
                            <div v-for="match in matches" :key="match.id" class="bg-white border border-slate-200/80 rounded-2xl p-5 flex flex-col md:flex-row items-center justify-between gap-4">
                                <div class="space-y-1">
                                    <div class="flex items-center gap-2">
                                        <span class="bg-slate-100 text-slate-700 text-[10px] font-black uppercase px-2.5 py-0.5 rounded-full tracking-wider">{{ match.round_name }}</span>
                                        <span v-if="match.bracket_type" class="bg-amber-100 text-amber-700 border border-amber-300/50 text-[10px] font-bold px-2 py-0.5 rounded">{{ match.bracket_type }}</span>
                                        <span class="text-xs text-slate-400">BO{{ match.best_of }}</span>
                                        <span class="text-xs text-slate-400 font-bold">({{ match.match_code || 'LIGA' }})</span>
                                    </div>
                                    <div class="text-slate-700 font-black text-base uppercase">
                                        {{ match.team_a ? match.team_a.name : 'TBD' }} 
                                        <span class="text-slate-400 px-2">VS</span> 
                                        {{ match.team_b ? match.team_b.name : 'TBD' }}
                                    </div>
                                </div>

                                <div class="flex items-center gap-4">
                                    <div v-if="match.winner_team_id" class="text-right">
                                        <span class="text-xs text-slate-400 font-bold block uppercase">Pemenang</span>
                                        <strong class="text-yellow-700 text-sm font-black uppercase">{{ match.winner_team.name }}</strong>
                                    </div>
                                    <div class="flex gap-2">
                                        <button 
                                            @click="openEditMatchModal(match)"
                                            class="bg-slate-900 hover:bg-slate-100 text-slate-700 border border-slate-200 hover:border-slate-600 px-4 py-2.5 rounded-xl text-xs font-bold transition"
                                        >
                                            ✏️ Edit
                                        </button>
                                        <button 
                                            @click="openScoreModal(match)"
                                            v-if="match.team_a_id && match.team_b_id"
                                            class="bg-slate-100 hover:bg-slate-700 text-slate-800 border border-slate-200 hover:border-slate-500 px-4 py-2.5 rounded-xl text-xs font-bold transition"
                                        >
                                            {{ match.games.length > 0 ? '🎮 Score Map' : '🎮 Score Map' }}
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Tab content: Standings & Awards -->
                    <div v-if="adminTab === 'standings'" class="grid grid-cols-1 md:grid-cols-3 gap-8">
                        <!-- Standings table -->
                        <div class="md:col-span-2 bg-white border border-slate-200 rounded-3xl p-6 shadow-xl space-y-4">
                            <h4 class="text-lg font-black uppercase text-slate-700 tracking-wide border-b border-slate-200 pb-2">Klasemen Liga Saat Ini</h4>
                            <div class="overflow-x-auto">
                                <table class="w-full text-left text-xs border-collapse">
                                    <thead>
                                        <tr class="border-b border-slate-200 text-slate-400 uppercase font-black tracking-wider">
                                            <th class="p-3 text-center">Rank</th>
                                            <th class="p-3">Tim</th>
                                            <th class="p-3 text-center">Match W/L</th>
                                            <th class="p-3 text-center">Net Games</th>
                                            <th class="p-3 text-center">Points</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="row in standings" :key="row.team_id" class="border-b border-slate-200/40 hover:bg-slate-100/10">
                                            <td class="p-3 text-center font-bold text-slate-600">#{{ row.rank }}</td>
                                            <td class="p-3 font-bold uppercase text-slate-800">{{ row.name }}</td>
                                            <td class="p-3 text-center font-semibold text-slate-600">{{ row.wins }}-{{ row.losses }}</td>
                                            <td class="p-3 text-center font-bold" :class="row.net_games >= 0 ? 'text-yellow-700' : 'text-red-600'">{{ row.net_games }}</td>
                                            <td class="p-3 text-center font-extrabold text-slate-800">{{ row.points }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Awards locked display -->
                        <div class="bg-white border border-slate-200 rounded-3xl p-6 shadow-xl space-y-4">
                            <h4 class="text-lg font-black uppercase text-slate-700 tracking-wide border-b border-slate-200 pb-2">Tournament Awards</h4>
                            <div v-if="awards.length === 0" class="text-center py-8 text-slate-400 text-xs font-semibold">Belum dikunci. Tekan tombol "Kunci Official Awards" di atas untuk memproses data rating pemain secara resmi.</div>
                            <div v-else class="space-y-3">
                                <div v-for="award in awards" :key="award.id" class="bg-slate-50 border border-slate-200 rounded-xl p-3 flex justify-between items-center">
                                    <div>
                                        <span class="text-[9px] uppercase font-black text-yellow-600 block">{{ award.award_type.replace('best_', 'BEST ').toUpperCase() }}</span>
                                        <strong class="text-sm font-black text-slate-800 uppercase">{{ award.player.name }}</strong>
                                        <span class="text-[10px] text-slate-400 uppercase block">{{ award.player.team.name }}</span>
                                    </div>
                                    <div class="text-right">
                                        <span class="text-[9px] uppercase font-bold text-slate-400 block">Avg Rating</span>
                                        <strong class="text-yellow-700 text-base font-extrabold">{{ award.avg_rating }}</strong>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Tab content: Teams & Players -->
                    <div v-if="adminTab === 'teams'" class="grid grid-cols-1 md:grid-cols-2 gap-8">
                        <!-- Manage Teams -->
                        <div class="bg-white border border-slate-200 rounded-3xl p-6 shadow-xl space-y-6">
                            <h4 class="text-lg font-black uppercase text-slate-700 tracking-wide border-b border-slate-200 pb-2">Daftar Tim Turnamen</h4>
                            
                            <form @submit.prevent="submitTeam" class="flex gap-2">
                                <input v-model="teamForm.name" type="text" placeholder="Nama Tim (e.g. EVOS)" class="flex-1 bg-white border border-slate-200 rounded-xl px-4 py-2.5 text-sm focus:border-yellow-500 focus:ring-0 text-slate-800" required />
                                <button type="submit" class="bg-yellow-500 hover:bg-yellow-600 text-slate-900 font-bold px-4 py-2.5 rounded-xl font-bold text-xs uppercase tracking-wide transition">Tambah</button>
                            </form>

                            <div class="space-y-2 max-h-[300px] overflow-y-auto">
                                <div v-for="team in teams" :key="team.id" class="flex items-center justify-between bg-slate-50 px-4 py-3 rounded-xl border border-slate-200/80">
                                    <div>
                                        <strong class="text-slate-800 font-bold uppercase">{{ team.name }}</strong>
                                        <span class="text-xs text-slate-400 block">{{ team.players_count }} Pemain Terdaftar</span>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <button @click="openEditTeamModal(team)" class="text-yellow-700 hover:text-yellow-600 font-bold text-xs transition">Edit</button>
                                        <button @click="deleteTeam(team.id)" class="text-red-400 hover:text-red-300 font-bold text-xs transition">Hapus</button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Manage Players -->
                        <div class="bg-white border border-slate-200 rounded-3xl p-6 shadow-xl space-y-6">
                            <h4 class="text-lg font-black uppercase text-slate-700 tracking-wide border-b border-slate-200 pb-2">Daftar Pemain (Roster)</h4>
                            
                            <form @submit.prevent="submitPlayer" class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                                <select v-model="playerForm.team_id" class="bg-white border border-slate-200 rounded-xl px-3 py-2 text-xs text-slate-800" required>
                                    <option value="" disabled selected>Pilih Tim</option>
                                    <option v-for="t in teams" :key="t.id" :value="t.id">{{ t.name }}</option>
                                </select>
                                <input v-model="playerForm.name" type="text" placeholder="Nama Pemain" class="bg-white border border-slate-200 rounded-xl px-3 py-2 text-xs focus:border-yellow-500 focus:ring-0 text-slate-800" required />
                                <div class="flex gap-2">
                                    <select v-model="playerForm.role" class="flex-1 bg-white border border-slate-200 rounded-xl px-3 py-2 text-xs text-slate-800" required>
                                        <option value="gold_lane">Gold Lane</option>
                                        <option value="exp_lane">Exp Lane</option>
                                        <option value="mid_lane">Mid Lane</option>
                                        <option value="jungle">Jungle</option>
                                        <option value="roam">Roamer</option>
                                    </select>
                                    <button type="submit" class="bg-yellow-500 hover:bg-yellow-600 text-slate-900 font-bold px-3 py-2 rounded-xl font-bold text-xs uppercase tracking-wide transition">Tambah</button>
                                </div>
                            </form>

                            <div class="space-y-2 max-h-[300px] overflow-y-auto">
                                <div v-for="player in players" :key="player.id" class="flex items-center justify-between bg-slate-50 px-4 py-2.5 rounded-xl border border-slate-200/80">
                                    <div>
                                        <strong class="text-slate-800 font-bold uppercase text-xs">{{ player.name }}</strong>
                                        <span class="text-[10px] text-slate-400 uppercase block">Tim: {{ player.team.name }} | Role: {{ player.role.replace('_', ' ') }}</span>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <button @click="openEditPlayerModal(player)" class="text-yellow-700 hover:text-yellow-600 font-bold text-xs transition">Edit</button>
                                        <button @click="deletePlayer(player.id)" class="text-red-400 hover:text-red-300 font-bold text-xs transition">Hapus</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>

                <!-- Input Score Modal (Fullscreen Backdrop) -->
                <div v-if="selectedMatch" class="fixed inset-0 z-50 overflow-y-auto bg-black/80 backdrop-blur-sm flex items-center justify-center p-4">
                    <div class="bg-white border border-slate-200 rounded-3xl w-full max-w-4xl max-h-[90vh] overflow-y-auto shadow-2xl">
                        
                        <div class="bg-slate-50 px-6 py-4 flex items-center justify-between border-b border-slate-200">
                            <div>
                                <span class="bg-yellow-100 border border-yellow-800/40 text-yellow-700 text-[10px] font-black uppercase px-2 py-0.5 rounded">FORM SKORING GAME</span>
                                <h4 class="text-lg font-black text-slate-800 uppercase tracking-wide mt-1">
                                    Input Score Map: {{ selectedMatch.team_a.name }} vs {{ selectedMatch.team_b.name }}
                                </h4>
                            </div>
                            <button @click="selectedMatch = null" class="text-slate-600 hover:text-white font-extrabold text-sm uppercase">Tutup</button>
                        </div>

                        <form @submit.prevent="submitGameScore" class="p-6 space-y-6">
                            
                            <!-- Game Level settings -->
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 bg-slate-50 p-4 rounded-2xl border border-slate-200">
                                <div>
                                    <label class="block text-[10px] uppercase font-black text-slate-400 mb-1.5">Game Number (Map)</label>
                                    <select v-model="activeGameNumber" class="w-full bg-white border border-slate-200 rounded-xl px-3 py-2.5 text-xs text-slate-800" required>
                                        <option v-for="n in selectedMatch.best_of" :key="n" :value="n">Game #{{ n }}</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-[10px] uppercase font-black text-slate-400 mb-1.5">Pemenang Map</label>
                                    <select v-model="gameWinnerId" class="w-full bg-white border border-slate-200 rounded-xl px-3 py-2.5 text-xs text-slate-800 font-bold" required>
                                        <option value="" disabled selected>Pilih Pemenang</option>
                                        <option :value="selectedMatch.team_a_id">{{ selectedMatch.team_a.name }}</option>
                                        <option :value="selectedMatch.team_b_id">{{ selectedMatch.team_b.name }}</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-[10px] uppercase font-black text-slate-400 mb-1.5">Durasi Game (Detik)</label>
                                    <input v-model="gameDuration" type="number" min="300" max="7200" class="w-full bg-white border border-slate-200 rounded-xl px-3 py-2.5 text-xs text-slate-800 font-mono" required />
                                </div>
                            </div>

                            <!-- Individual Player Game Stats Input -->
                            <div class="space-y-4">
                                <h5 class="text-xs font-black uppercase text-slate-600 tracking-wider border-b border-slate-200 pb-2">INPUT RATING & KDA PEMAIN (10 PEMAIN)</h5>
                                
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <!-- Team A Column -->
                                    <div class="space-y-4">
                                        <h6 class="text-xs font-black text-yellow-700 uppercase tracking-widest">{{ selectedMatch.team_a.name }}</h6>
                                        <div 
                                            v-for="(player, index) in playerStatsInput.filter(p => p.team_id === selectedMatch.team_a_id)" 
                                            :key="player.player_id"
                                            class="bg-slate-900/40 p-4 rounded-xl border border-slate-200/80 space-y-3"
                                        >
                                            <div class="flex items-center justify-between">
                                                <div>
                                                    <span class="text-xs font-black text-slate-800 block uppercase">{{ player.name }}</span>
                                                    <span class="text-[9px] text-slate-400 uppercase">{{ player.role.replace('_', ' ') }}</span>
                                                </div>
                                                <label class="flex items-center gap-1 cursor-pointer bg-slate-100 px-2 py-0.5 rounded text-[9px] font-black uppercase">
                                                    <input 
                                                        type="checkbox" 
                                                        v-model="player.is_mvp" 
                                                        @change="handleMvpCheck(playerStatsInput.indexOf(player))"
                                                        class="rounded border-slate-200 bg-slate-900 text-yellow-600 focus:ring-0 w-3 h-3"
                                                    />
                                                    MVP
                                                </label>
                                            </div>

                                            <div class="grid grid-cols-5 gap-1.5 text-xs">
                                                <div>
                                                    <label class="block text-[8px] uppercase font-bold text-slate-400">Hero</label>
                                                    <input v-model="player.hero" type="text" class="w-full bg-white border border-slate-200 rounded px-1.5 py-1 text-slate-800 text-center" required />
                                                </div>
                                                <div>
                                                    <label class="block text-[8px] uppercase font-bold text-slate-400">Kills</label>
                                                    <input v-model="player.kills" type="number" min="0" class="w-full bg-white border border-slate-200 rounded px-1.5 py-1 text-slate-800 text-center" required />
                                                </div>
                                                <div>
                                                    <label class="block text-[8px] uppercase font-bold text-slate-400">Deaths</label>
                                                    <input v-model="player.deaths" type="number" min="0" class="w-full bg-white border border-slate-200 rounded px-1.5 py-1 text-slate-800 text-center" required />
                                                </div>
                                                <div>
                                                    <label class="block text-[8px] uppercase font-bold text-slate-400">Assists</label>
                                                    <input v-model="player.assists" type="number" min="0" class="w-full bg-white border border-slate-200 rounded px-1.5 py-1 text-slate-800 text-center" required />
                                                </div>
                                                <div>
                                                    <label class="block text-[8px] uppercase font-bold text-slate-400">Rating</label>
                                                    <input v-model="player.rating" type="number" step="0.1" min="0" max="15" class="w-full bg-white border border-slate-200 rounded px-1.5 py-1 text-yellow-700 font-extrabold text-center" required />
                                                </div>
                                            </div>
                                            <div>
                                                <label class="block text-[8px] uppercase font-bold text-slate-400 mb-0.5">Gold Earned</label>
                                                <input v-model="player.gold_earned" type="number" min="0" class="w-full bg-white border border-slate-200 rounded px-2 py-1 text-yellow-600 text-xs font-semibold" required />
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Team B Column -->
                                    <div class="space-y-4">
                                        <h6 class="text-xs font-black text-red-600 uppercase tracking-widest">{{ selectedMatch.team_b.name }}</h6>
                                        <div 
                                            v-for="(player, index) in playerStatsInput.filter(p => p.team_id === selectedMatch.team_b_id)" 
                                            :key="player.player_id"
                                            class="bg-slate-900/40 p-4 rounded-xl border border-slate-200/80 space-y-3"
                                        >
                                            <div class="flex items-center justify-between">
                                                <div>
                                                    <span class="text-xs font-black text-slate-800 block uppercase">{{ player.name }}</span>
                                                    <span class="text-[9px] text-slate-400 uppercase">{{ player.role.replace('_', ' ') }}</span>
                                                </div>
                                                <label class="flex items-center gap-1 cursor-pointer bg-slate-100 px-2 py-0.5 rounded text-[9px] font-black uppercase">
                                                    <input 
                                                        type="checkbox" 
                                                        v-model="player.is_mvp" 
                                                        @change="handleMvpCheck(playerStatsInput.indexOf(player))"
                                                        class="rounded border-slate-200 bg-slate-900 text-yellow-600 focus:ring-0 w-3 h-3"
                                                    />
                                                    MVP
                                                </label>
                                            </div>

                                            <div class="grid grid-cols-5 gap-1.5 text-xs">
                                                <div>
                                                    <label class="block text-[8px] uppercase font-bold text-slate-400">Hero</label>
                                                    <input v-model="player.hero" type="text" class="w-full bg-white border border-slate-200 rounded px-1.5 py-1 text-slate-800 text-center" required />
                                                </div>
                                                <div>
                                                    <label class="block text-[8px] uppercase font-bold text-slate-400">Kills</label>
                                                    <input v-model="player.kills" type="number" min="0" class="w-full bg-white border border-slate-200 rounded px-1.5 py-1 text-slate-800 text-center" required />
                                                </div>
                                                <div>
                                                    <label class="block text-[8px] uppercase font-bold text-slate-400">Deaths</label>
                                                    <input v-model="player.deaths" type="number" min="0" class="w-full bg-white border border-slate-200 rounded px-1.5 py-1 text-slate-800 text-center" required />
                                                </div>
                                                <div>
                                                    <label class="block text-[8px] uppercase font-bold text-slate-400">Assists</label>
                                                    <input v-model="player.assists" type="number" min="0" class="w-full bg-white border border-slate-200 rounded px-1.5 py-1 text-slate-800 text-center" required />
                                                </div>
                                                <div>
                                                    <label class="block text-[8px] uppercase font-bold text-slate-400">Rating</label>
                                                    <input v-model="player.rating" type="number" step="0.1" min="0" max="15" class="w-full bg-white border border-slate-200 rounded px-1.5 py-1 text-yellow-700 font-extrabold text-center" required />
                                                </div>
                                            </div>
                                            <div>
                                                <label class="block text-[8px] uppercase font-bold text-slate-400 mb-0.5">Gold Earned</label>
                                                <input v-model="player.gold_earned" type="number" min="0" class="w-full bg-white border border-slate-200 rounded px-2 py-1 text-yellow-600 text-xs font-semibold" required />
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="flex justify-end gap-3 pt-4 border-t border-slate-200">
                                <button 
                                    type="button" 
                                    @click="selectedMatch = null" 
                                    class="bg-slate-100 hover:bg-slate-700 text-slate-700 px-5 py-2.5 rounded-xl text-xs font-bold transition"
                                >
                                    Batal
                                </button>
                                <button 
                                    type="submit" 
                                    :disabled="scoreForm.processing"
                                    class="bg-yellow-500 hover:bg-yellow-600 text-slate-900 font-bold px-5 py-2.5 rounded-xl text-xs font-extrabold uppercase tracking-wide transition shadow-md shadow-none"
                                >
                                    Simpan Hasil Map
                                </button>
                            </div>

                        </form>
                    </div>
                </div>

                <!-- Edit Match Modal (Fullscreen Backdrop) -->
                <div v-if="selectedEditMatch" class="fixed inset-0 z-50 overflow-y-auto bg-black/80 backdrop-blur-sm flex items-center justify-center p-4">
                    <div class="bg-white border border-slate-200 rounded-3xl w-full max-w-lg shadow-2xl">
                        
                        <div class="bg-slate-50 px-6 py-4 flex items-center justify-between border-b border-slate-200">
                            <div>
                                <span class="bg-yellow-100 border border-yellow-800/40 text-yellow-700 text-[10px] font-black uppercase px-2 py-0.5 rounded">EDIT PERTANDINGAN</span>
                                <h4 class="text-base font-black text-slate-800 uppercase tracking-wide mt-1">
                                    Override Detail Match
                                </h4>
                            </div>
                            <button @click="selectedEditMatch = null" class="text-slate-600 hover:text-white font-extrabold text-sm uppercase">Tutup</button>
                        </div>

                        <form @submit.prevent="submitEditMatch" class="p-6 space-y-4">
                            <div>
                                <label class="block text-xs uppercase tracking-wider text-slate-600 font-bold mb-1.5">Nama Babak / Round</label>
                                <input v-model="editMatchForm.round_name" type="text" class="w-full bg-white border border-slate-200 rounded-xl px-4 py-2.5 text-sm text-slate-800" required />
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs uppercase tracking-wider text-slate-600 font-bold mb-1.5">Tim A</label>
                                    <select v-model="editMatchForm.team_a_id" class="w-full bg-white border border-slate-200 rounded-xl px-3 py-2.5 text-xs text-slate-800">
                                        <option value="">Pilih Tim A (TBD)</option>
                                        <option v-for="t in teams" :key="t.id" :value="t.id">{{ t.name }}</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs uppercase tracking-wider text-slate-600 font-bold mb-1.5">Tim B</label>
                                    <select v-model="editMatchForm.team_b_id" class="w-full bg-white border border-slate-200 rounded-xl px-3 py-2.5 text-xs text-slate-800">
                                        <option value="">Pilih Tim B (TBD)</option>
                                        <option v-for="t in teams" :key="t.id" :value="t.id">{{ t.name }}</option>
                                    </select>
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs uppercase tracking-wider text-slate-600 font-bold mb-1.5">Jadwal Pertandingan</label>
                                    <input v-model="editMatchForm.scheduled_at" type="datetime-local" class="w-full bg-white border border-slate-200 rounded-xl px-3 py-2.5 text-xs text-slate-800" />
                                </div>
                                <div>
                                    <label class="block text-xs uppercase tracking-wider text-slate-600 font-bold mb-1.5">Format Match (BO)</label>
                                    <select v-model="editMatchForm.best_of" class="w-full bg-white border border-slate-200 rounded-xl px-3 py-2.5 text-xs text-slate-800" required>
                                        <option :value="1">BO1 (Best of 1)</option>
                                        <option :value="3">BO3 (Best of 3)</option>
                                        <option :value="5">BO5 (Best of 5)</option>
                                        <option :value="7">BO7 (Best of 7)</option>
                                    </select>
                                </div>
                            </div>

                            <div class="flex justify-end gap-3 pt-4 border-t border-slate-200">
                                <button 
                                    type="button" 
                                    @click="selectedEditMatch = null" 
                                    class="bg-slate-100 hover:bg-slate-700 text-slate-700 px-5 py-2.5 rounded-xl text-xs font-bold transition"
                                >
                                    Batal
                                </button>
                                <button 
                                    type="submit" 
                                    :disabled="editMatchForm.processing"
                                    class="bg-yellow-500 hover:bg-yellow-600 text-slate-900 font-bold px-5 py-2.5 rounded-xl text-xs font-extrabold uppercase tracking-wide transition shadow-md shadow-none"
                                >
                                    Simpan Perubahan
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Reset Tournament Modal -->
                <div v-if="isResetModalOpen" class="fixed inset-0 z-50 bg-black/80 backdrop-blur-sm flex items-center justify-center p-4">
                    <div class="bg-white border border-red-500/30 rounded-3xl w-full max-w-md shadow-2xl p-6 space-y-4">
                        <div class="text-center space-y-2">
                            <span class="text-4xl">⚠️</span>
                            <h4 class="text-lg font-black text-red-600 uppercase tracking-wide">RESET SELURUH TURNAMEN</h4>
                            <p class="text-xs text-slate-600">Tindakan ini akan menghapus semua konfigurasi kompetisi, babak, jadwal, hasil skor map, dan penghargaan secara permanen.</p>
                        </div>

                        <form @submit.prevent="submitReset" class="space-y-4 pt-2">
                            <label class="flex items-start gap-3 bg-rose-50 border border-rose-200 p-4 rounded-2xl cursor-pointer">
                                <input 
                                    type="checkbox" 
                                    v-model="resetForm.delete_teams" 
                                    class="rounded border-slate-200 bg-slate-900 text-rose-500 focus:ring-0 mt-0.5"
                                />
                                <div>
                                    <span class="text-xs font-bold text-slate-800 block">Hapus Semua Tim & Roster Pemain</span>
                                    <span class="text-[10px] text-slate-600 block">Tandai ini jika ingin mengosongkan data tim & 40+ pemain dari database juga.</span>
                                </div>
                            </label>

                            <div class="flex gap-2 pt-2">
                                <button 
                                    type="button" 
                                    @click="isResetModalOpen = false" 
                                    class="flex-1 bg-slate-100 hover:bg-slate-700 text-slate-700 py-2.5 rounded-xl text-xs font-bold transition"
                                >
                                    Batal
                                </button>
                                <button 
                                    type="submit" 
                                    :disabled="resetForm.processing"
                                    class="flex-1 bg-rose-600 hover:bg-rose-500 text-white py-2.5 rounded-xl text-xs font-extrabold uppercase tracking-wide transition shadow-md shadow-rose-500/10"
                                >
                                    Ya, Reset Sekarang
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Edit Team Modal -->
                <div v-if="selectedEditTeam" class="fixed inset-0 z-50 bg-black/80 backdrop-blur-sm flex items-center justify-center p-4">
                    <div class="bg-white border border-slate-200 rounded-3xl w-full max-w-md shadow-2xl">
                        
                        <div class="bg-slate-50 px-6 py-4 flex items-center justify-between border-b border-slate-200">
                            <div>
                                <span class="bg-yellow-100 border border-yellow-800/40 text-yellow-700 text-[10px] font-black uppercase px-2 py-0.5 rounded">EDIT TIM</span>
                                <h4 class="text-base font-black text-slate-800 uppercase tracking-wide mt-1">
                                    Edit Detail Tim
                                </h4>
                            </div>
                            <button @click="selectedEditTeam = null" class="text-slate-600 hover:text-white font-extrabold text-sm uppercase">Tutup</button>
                        </div>

                        <form @submit.prevent="submitEditTeam" class="p-6 space-y-4">
                            <div>
                                <label class="block text-xs uppercase tracking-wider text-slate-600 font-bold mb-1.5">Nama Tim</label>
                                <input v-model="editTeamForm.name" type="text" class="w-full bg-white border border-slate-200 rounded-xl px-4 py-2.5 text-sm text-slate-800" required />
                            </div>

                            <div>
                                <label class="block text-xs uppercase tracking-wider text-slate-600 font-bold mb-1.5">Logo URL (Opsional)</label>
                                <input v-model="editTeamForm.logo" type="text" placeholder="Contoh: https://example.com/logo.png" class="w-full bg-white border border-slate-200 rounded-xl px-4 py-2.5 text-sm text-slate-800" />
                            </div>

                            <div class="flex justify-end gap-3 pt-4 border-t border-slate-200">
                                <button 
                                    type="button" 
                                    @click="selectedEditTeam = null" 
                                    class="bg-slate-100 hover:bg-slate-700 text-slate-700 px-5 py-2.5 rounded-xl text-xs font-bold transition"
                                >
                                    Batal
                                </button>
                                <button 
                                    type="submit" 
                                    :disabled="editTeamForm.processing"
                                    class="bg-yellow-500 hover:bg-yellow-600 text-slate-900 font-bold px-5 py-2.5 rounded-xl text-xs font-extrabold uppercase tracking-wide transition shadow-md shadow-none"
                                >
                                    Simpan Perubahan
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Edit Player Modal -->
                <div v-if="selectedEditPlayer" class="fixed inset-0 z-50 bg-black/80 backdrop-blur-sm flex items-center justify-center p-4">
                    <div class="bg-white border border-slate-200 rounded-3xl w-full max-w-md shadow-2xl">
                        
                        <div class="bg-slate-50 px-6 py-4 flex items-center justify-between border-b border-slate-200">
                            <div>
                                <span class="bg-yellow-100 border border-yellow-800/40 text-yellow-700 text-[10px] font-black uppercase px-2 py-0.5 rounded">EDIT PEMAIN</span>
                                <h4 class="text-base font-black text-slate-800 uppercase tracking-wide mt-1">
                                    Edit Data Pemain / Roster
                                </h4>
                            </div>
                            <button @click="selectedEditPlayer = null" class="text-slate-600 hover:text-white font-extrabold text-sm uppercase">Tutup</button>
                        </div>

                        <form @submit.prevent="submitEditPlayer" class="p-6 space-y-4">
                            <div>
                                <label class="block text-xs uppercase tracking-wider text-slate-600 font-bold mb-1.5">Nama Pemain</label>
                                <input v-model="editPlayerForm.name" type="text" class="w-full bg-white border border-slate-200 rounded-xl px-4 py-2.5 text-sm text-slate-800" required />
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs uppercase tracking-wider text-slate-600 font-bold mb-1.5">Tim Asosiasi</label>
                                    <select v-model="editPlayerForm.team_id" class="w-full bg-white border border-slate-200 rounded-xl px-3 py-2.5 text-xs text-slate-800" required>
                                        <option v-for="t in teams" :key="t.id" :value="t.id">{{ t.name }}</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs uppercase tracking-wider text-slate-600 font-bold mb-1.5">Role Lane</label>
                                    <select v-model="editPlayerForm.role" class="w-full bg-white border border-slate-200 rounded-xl px-3 py-2.5 text-xs text-slate-800" required>
                                        <option value="gold_lane">Gold Lane</option>
                                        <option value="exp_lane">Exp Lane</option>
                                        <option value="mid_lane">Mid Lane</option>
                                        <option value="jungle">Jungle</option>
                                        <option value="roam">Roamer</option>
                                    </select>
                                </div>
                            </div>

                            <div class="flex justify-end gap-3 pt-4 border-t border-slate-200">
                                <button 
                                    type="button" 
                                    @click="selectedEditPlayer = null" 
                                    class="bg-slate-100 hover:bg-slate-700 text-slate-700 px-5 py-2.5 rounded-xl text-xs font-bold transition"
                                >
                                    Batal
                                </button>
                                <button 
                                    type="submit" 
                                    :disabled="editPlayerForm.processing"
                                    class="bg-yellow-500 hover:bg-yellow-600 text-slate-900 font-bold px-5 py-2.5 rounded-xl text-xs font-extrabold uppercase tracking-wide transition shadow-md shadow-none"
                                >
                                    Simpan Perubahan
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
