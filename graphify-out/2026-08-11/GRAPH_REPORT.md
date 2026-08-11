# Graph Report - tournament_mlbb  (2026-08-11)

## Corpus Check
- 127 files · ~68,412 words
- Verdict: corpus is large enough that graph structure adds value.

## Summary
- 621 nodes · 974 edges · 64 communities (54 shown, 10 thin omitted)
- Extraction: 93% EXTRACTED · 7% INFERRED · 0% AMBIGUOUS · INFERRED: 69 edges (avg confidence: 0.8)
- Token cost: 0 input · 0 output

## Graph Freshness
- Built from commit: `df224edc`
- Run `git rev-parse HEAD` and compare to check if the graph is stale.
- Run `graphify update .` after code changes (no API cost).

## Community Hubs (Navigation)
- MlMatch
- Illuminate\Http\RedirectResponse
- InputError.vue
- User
- Dashboard.vue
- composer.json
- devDependencies
- Welcome.vue
- scripts
- What You Must Do When Invoked
- LoginRequest
- AuthenticatedLayout.vue
- graphify reference: extra exports and benchmark
- compilerOptions
- README.md
- graphify reference: query, path, explain
- UserFactory
- DeleteUserForm.vue
- graphify reference: add a URL and watch a folder
- graphify reference: commit hook and native CLAUDE.md integration
- graphify reference: incremental update and cluster-only
- ExampleTest
- graphify reference: GitHub clone and cross-repo merge
- graphify reference: transcribe video and audio
- rules/graphify.md
- workflows/graphify.md
- bootstrap/app.php
- CLAUDE.md
- .claude/CLAUDE.md
- extraction-spec.md
- Modal.vue
- VerifyEmail.vue
- Login.vue
- ResetPassword.vue
- TextInput.vue
- Dropdown.vue

## God Nodes (most connected - your core abstractions)
1. `MlMatch` - 29 edges
2. `User` - 27 edges
3. `Controller` - 24 edges
4. `Team` - 23 edges
5. `Stage` - 22 edges
6. `Player` - 21 edges
7. `TestCase` - 20 edges
8. `CompetitionSetup` - 18 edges
9. `TournamentAdminController` - 17 edges
10. `What You Must Do When Invoked` - 12 edges

## Surprising Connections (you probably didn't know these)
- `up()` --calls--> `PlayerGameStat`  [INFERRED]
  database/migrations/2026_08_11_000002_clean_hero_names_typos.php → app/Models/PlayerGameStat.php
- `TournamentServicesTest` --inherits--> `TestCase`  [EXTRACTED]
  tests/Unit/TournamentServicesTest.php → tests/TestCase.php
- `AuthenticatedSessionController` --inherits--> `Controller`  [EXTRACTED]
  app/Http/Controllers/Auth/AuthenticatedSessionController.php → app/Http/Controllers/Controller.php
- `ConfirmablePasswordController` --inherits--> `Controller`  [EXTRACTED]
  app/Http/Controllers/Auth/ConfirmablePasswordController.php → app/Http/Controllers/Controller.php
- `EmailVerificationNotificationController` --inherits--> `Controller`  [EXTRACTED]
  app/Http/Controllers/Auth/EmailVerificationNotificationController.php → app/Http/Controllers/Controller.php

## Import Cycles
- None detected.

## Communities (64 total, 10 thin omitted)

### Community 0 - "MlMatch"
Cohesion: 0.07
Nodes (21): CompetitionSetup, Game, MlMatch, Player, PlayerGameStat, Stage, Team, TournamentAward (+13 more)

### Community 1 - "Illuminate\Http\RedirectResponse"
Cohesion: 0.07
Nodes (19): AuthenticatedSessionController, ConfirmablePasswordController, EmailVerificationNotificationController, EmailVerificationPromptController, NewPasswordController, PasswordController, PasswordResetLinkController, RegisteredUserController (+11 more)

### Community 2 - "InputError.vue"
Cohesion: 0.21
Nodes (4): form, form, form, form

### Community 3 - "User"
Cohesion: 0.07
Nodes (17): User, DatabaseSeeder, Illuminate\Database\Console\Seeds\WithoutModelEvents, Illuminate\Database\Eloquent\Factories\HasFactory, Illuminate\Foundation\Auth\User, Illuminate\Foundation\Testing\RefreshDatabase, Illuminate\Foundation\Testing\TestCase, Illuminate\Notifications\Notifiable (+9 more)

### Community 4 - "Dashboard.vue"
Cohesion: 0.05
Nodes (26): activeDateTab, activeGameNumber, adminTab, currentGroup, editMatchForm, editPlayerForm, editTeamForm, gameDuration (+18 more)

### Community 5 - "composer.json"
Cohesion: 0.04
Nodes (44): pestphp/pest-plugin, php-http/discovery, autoload, autoload-dev, psr-4, psr-4, config, allow-plugins (+36 more)

### Community 6 - "devDependencies"
Cohesion: 0.06
Nodes (31): AppServiceProvider, autoprefixer, concurrently, Illuminate\Support\ServiceProvider, @inertiajs/vue3, laravel-vite-plugin, devDependencies, autoprefixer (+23 more)

### Community 7 - "Welcome.vue"
Cohesion: 0.07
Nodes (22): activeDateTab, activeStageType, activeTab, activeTimeTab, currentGroup, expandedMatches, filteredPlayerStats, groupedMatches (+14 more)

### Community 8 - "scripts"
Cohesion: 0.08
Nodes (26): scripts, dev, post-autoload-dump, post-create-project-cmd, post-root-package-install, post-update-cmd, pre-package-uninstall, setup (+18 more)

### Community 9 - "What You Must Do When Invoked"
Cohesion: 0.08
Nodes (24): For /graphify add and --watch, For /graphify query, For the commit hook and native CLAUDE.md integration, For --update and --cluster-only, /graphify, Honesty Rules, Interpreter guard for subcommands, Part A - Structural extraction for code files (+16 more)

### Community 10 - "LoginRequest"
Cohesion: 0.27
Nodes (3): LoginRequest, ProfileUpdateRequest, Illuminate\Foundation\Http\FormRequest

### Community 11 - "AuthenticatedLayout.vue"
Cohesion: 0.18
Nodes (5): classes, props, classes, props, showingNavigationDropdown

### Community 12 - "graphify reference: extra exports and benchmark"
Cohesion: 0.22
Nodes (8): graphify reference: extra exports and benchmark, Step 6b - Wiki (only if --wiki flag), Step 7 - Neo4j export (only if --neo4j or --neo4j-push flag), Step 7a - FalkorDB export (only if --falkordb or --falkordb-push flag), Step 7b - SVG export (only if --svg flag), Step 7c - GraphML export (only if --graphml flag), Step 7d - MCP server (only if --mcp flag), Step 8 - Token reduction benchmark (only if total_words > 5000)

### Community 13 - "compilerOptions"
Cohesion: 0.22
Nodes (8): compilerOptions, baseUrl, paths, exclude, ziggy-js, node_modules, public, ./vendor/tightenco/ziggy

### Community 14 - "README.md"
Cohesion: 0.25
Nodes (7): About Laravel, Agentic Development, Code of Conduct, Contributing, Learning Laravel, License, Security Vulnerabilities

### Community 15 - "graphify reference: query, path, explain"
Cohesion: 0.33
Nodes (5): For /graphify explain, For /graphify path, graphify reference: query, path, explain, Step 0 — Constrained query expansion (REQUIRED before traversal), Step 1 — Traversal

### Community 16 - "UserFactory"
Cohesion: 0.47
Nodes (3): UserFactory, Illuminate\Database\Eloquent\Factories\Factory, static

### Community 17 - "DeleteUserForm.vue"
Cohesion: 0.25
Nodes (5): closeModal(), confirmingUserDeletion, deleteUser(), form, passwordInput

### Community 18 - "graphify reference: add a URL and watch a folder"
Cohesion: 0.50
Nodes (3): For /graphify add, For --watch, graphify reference: add a URL and watch a folder

### Community 19 - "graphify reference: commit hook and native CLAUDE.md integration"
Cohesion: 0.50
Nodes (3): For git commit hook, For native CLAUDE.md integration, graphify reference: commit hook and native CLAUDE.md integration

### Community 20 - "graphify reference: incremental update and cluster-only"
Cohesion: 0.50
Nodes (3): For --cluster-only, For --update (incremental re-extraction), graphify reference: incremental update and cluster-only

### Community 57 - "Modal.vue"
Cohesion: 0.32
Nodes (7): close(), closeOnEscape(), dialog, emit, maxWidthClass, props, showSlot

### Community 58 - "VerifyEmail.vue"
Cohesion: 0.40
Nodes (3): form, props, verificationLinkSent

### Community 59 - "Login.vue"
Cohesion: 0.29
Nodes (4): emit, props, proxyChecked, form

### Community 61 - "TextInput.vue"
Cohesion: 0.25
Nodes (5): input, model, currentPasswordInput, form, passwordInput

### Community 63 - "Dropdown.vue"
Cohesion: 0.33
Nodes (4): alignmentClasses, open, props, widthClass

## Knowledge Gaps
- **200 isolated node(s):** `$schema`, `name`, `type`, `description`, `laravel` (+195 more)
  These have ≤1 connection - possible missing edges or undocumented components.
- **10 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `TestCase` connect `User` to `MlMatch`, `ResetPassword.vue`?**
  _High betweenness centrality (0.040) - this node is a cross-community bridge._
- **Why does `User` connect `User` to `MlMatch`, `Illuminate\Http\RedirectResponse`?**
  _High betweenness centrality (0.030) - this node is a cross-community bridge._
- **Why does `Controller` connect `Illuminate\Http\RedirectResponse` to `MlMatch`?**
  _High betweenness centrality (0.021) - this node is a cross-community bridge._
- **Are the 10 inferred relationships involving `MlMatch` (e.g. with `.index()` and `.generate()`) actually correct?**
  _`MlMatch` has 10 INFERRED edges - model-reasoned connections that need verification._
- **Are the 22 inferred relationships involving `User` (e.g. with `.store()` and `.run()`) actually correct?**
  _`User` has 22 INFERRED edges - model-reasoned connections that need verification._
- **What connects `$schema`, `name`, `type` to the rest of the system?**
  _200 weakly-connected nodes found - possible documentation gaps or missing edges._
- **Should `MlMatch` be split into smaller, more focused modules?**
  _Cohesion score 0.07052600646488393 - nodes in this community are weakly interconnected._