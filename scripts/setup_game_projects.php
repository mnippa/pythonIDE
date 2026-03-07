<?php
require_once __DIR__ . '/../config/database.php';

$pdo = new PDO(
    "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
    DB_USER,
    DB_PASS,
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]
);

// Find admin user
$userStmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
$userStmt->execute(['admin@pythonide.local']);
$admin = $userStmt->fetch();

if (!$admin) {
    fwrite(STDERR, "❌ Admin-User 'admin@pythonide.local' nicht gefunden.\n");
    exit(1);
}

$adminId = (int)$admin['id'];

// === BLACKJACK ===
$blackjackCode = <<<'PYTHON'
import idegui as ui
import random

# Global state - persisted between trigger calls
if 'game' not in globals():
    game = {
        'deck': None,
        'player_hand': [],
        'dealer_hand': [],
        'player_score': 0,
        'dealer_score': 0,
        'game_over': False,
        'message': '',
        'player_stand': False,
    }

def init_deck():
    """Erzeugt ein neues Kartendeck"""
    suits = ['♠', '♥', '♦', '♣']
    ranks = ['2', '3', '4', '5', '6', '7', '8', '9', '10', 'J', 'Q', 'K', 'A']
    deck = [(rank, suit) for suit in suits for rank in ranks]
    random.shuffle(deck)
    return deck

def card_value(rank):
    """Berechnet den Wert einer Karte"""
    if rank in ['J', 'Q', 'K']:
        return 10
    elif rank == 'A':
        return 11
    else:
        return int(rank)

def hand_value(hand):
    """Berechnet die beste Hand-Punktzahl (mit Ace-Adjusting)"""
    total = sum(card_value(rank) for rank, suit in hand)
    aces = sum(1 for rank, suit in hand if rank == 'A')
    
    while total > 21 and aces > 0:
        total -= 10
        aces -= 1
    
    return total

def format_hand(hand):
    """Formatiert eine Hand zur Anzeige"""
    return ' '.join(f"{rank}{suit}" for rank, suit in hand)

def new_game():
    """Startet ein neues Spiel"""
    global game
    game['deck'] = init_deck()
    game['player_hand'] = [game['deck'].pop(), game['deck'].pop()]
    game['dealer_hand'] = [game['deck'].pop(), game['deck'].pop()]
    game['game_over'] = False
    game['player_stand'] = False
    game['message'] = ''
    update_display()

def dealer_play():
    """KI: Dealer spielt automatisch (bis 17+)"""
    while hand_value(game['dealer_hand']) < 17:
        game['dealer_hand'].append(game['deck'].pop())

def check_winner():
    """Bestimmt den Gewinner"""
    player_val = hand_value(game['player_hand'])
    dealer_val = hand_value(game['dealer_hand'])
    
    if player_val > 21:
        return "❌ Bust! Du hast verloren!"
    elif dealer_val > 21:
        return "✅ Dealer büßt! Du gewinnst!"
    elif player_val > dealer_val:
        return "✅ Du gewinnst!"
    elif player_val < dealer_val:
        return "❌ Dealer gewinnt!"
    else:
        return "🤝 Unentschieden!"

def update_display():
    """Aktualisiert die Anzeige"""
    player_val = hand_value(game['player_hand'])
    dealer_val = hand_value(game['dealer_hand'])
    
    ui.set('player_cards', f"Deine Karten: {format_hand(game['player_hand'])}")
    ui.set('player_score', f"Punktzahl: {player_val}")
    
    if game['game_over']:
        ui.set('dealer_cards', f"Dealer-Karten: {format_hand(game['dealer_hand'])}")
        ui.set('dealer_score', f"Dealer-Punkte: {dealer_val}")
        ui.set('message', game['message'])
    else:
        ui.set('dealer_cards', f"Dealer-Karten: {game['dealer_hand'][0]} + [verborgen]")
        ui.set('dealer_score', "Dealer-Punkte: ?")
    
    # Button-Status aktualisieren
    hit_btn = ui.get('__hit_btn_element')
    stand_btn = ui.get('__stand_btn_element')
    if game['game_over']:
        ui.set('hit_enabled', 'disabled')
        ui.set('stand_enabled', 'disabled')
    else:
        ui.set('hit_enabled', '')
        ui.set('stand_enabled', '')

def on_hit(trigger):
    """Spieler zieht eine Karte"""
    if game['game_over'] or game['player_stand']:
        return
    
    game['player_hand'].append(game['deck'].pop())
    
    if hand_value(game['player_hand']) > 21:
        game['game_over'] = True
        game['message'] = '❌ Bust! Du hast verloren!'
    
    update_display()

def on_stand(trigger):
    """Spieler hält an und Dealer spielt"""
    if game['game_over']:
        return
    
    game['player_stand'] = True
    dealer_play()
    game['message'] = check_winner()
    game['game_over'] = True
    
    update_display()

def on_new_game(trigger):
    """Neues Spiel starten"""
    new_game()

# Initial setup
ui.clear()
ui.title('🎰 Blackjack gegen den Computer')

new_game()

# Display
ui.set('player_cards', '')
ui.set('player_score', '')
ui.set('dealer_cards', '')
ui.set('dealer_score', '')
ui.set('message', '')

# Buttons
ui.button('Hit').on_click(on_hit)
ui.button('Stand').on_click(on_stand)
ui.button('Neues Spiel').on_click(on_new_game)

ui.output().write('Viel Spaß beim Spielen!')
PYTHON;

// === KNIFFEL (YAHTZEE) ===
$kniffelCode = <<<'PYTHON'
import idegui as ui
import random

# Global state
if 'game' not in globals():
    game = {
        'dice': [1, 1, 1, 1, 1],
        'dice_held': [False, False, False, False, False],
        'rolls_left': 3,
        'round': 1,
        'scores': {},
        'game_over': False,
    }

def roll_dice():
    """Würfelt unhalbierte Würfel neu"""
    for i in range(5):
        if not game['dice_held'][i]:
            game['dice'][i] = random.randint(1, 6)

def toggle_hold(index):
    """Togelt den Hold-Status eines Würfels"""
    if index >= 0 and index < 5:
        game['dice_held'][index] = not game['dice_held'][index]

def get_score_options():
    """Berechnet mögliche Punktezahlen für aktuelle Würfel"""
    dice = game['dice']
    options = {}
    
    counts = [dice.count(i) for i in range(1, 7)]
    
    # Einzelnen Zahlen
    for num in range(1, 7):
        options[f'Summe {num}er'] = sum([x for x in dice if x == num])
    
    # Dreier
    for count in counts:
        if count >= 3:
            options['Dreierpasch'] = sum(dice)
            break
    
    # Viererpasch
    for count in counts:
        if count >= 4:
            options['Viererpasch'] = sum(dice)
            break
    
    # Full House
    if (3 in counts and 2 in counts) or (5 in counts):
        options['Full House'] = 25
    
    # Kleine Straße
    sorted_dice = sorted(set(dice))
    if sorted_dice == [1, 2, 3, 4, 5] or sorted_dice == [2, 3, 4, 5, 6]:
        options['Kleine Straße'] = 30
    
    # Große Straße
    if sorted_dice == [1, 2, 3, 4, 5] or sorted_dice == [2, 3, 4, 5, 6]:
        options['Große Straße'] = 40
    
    # Kniffel
    if all(x == dice[0] for x in dice):
        options['Kniffel'] = 50
    
    # Chance
    options['Chance'] = sum(dice)
    
    return options

def update_display():
    """Aktualisiert die Anzeige"""
    dice_str = ' '.join([
        f'[{game["dice"][i]}]' if game['dice_held'][i] else f'{game["dice"][i]}'
        for i in range(5)
    ])
    
    ui.set('dice_display', f'Würfel: {dice_str}')
    ui.set('rolls_left', f'Würfe übrig: {game["rolls_left"]}')
    ui.set('round', f'Runde {game["round"]}/13')
    
    score_str = '\n'.join([f'{cat}: {points}' for cat, points in game['scores'].items()])
    ui.set('scorecard', score_str or 'Noch keine Einträge')
    
    if game['rolls_left'] > 0:
        ui.set('message', 'Würfel halten (anklicken) oder neu würfeln')
    else:
        options = get_score_options()
        ui.set('message', f'Wähle eine Kategorie: {", ".join(list(options.keys())[:3])}...')

def on_roll(trigger):
    """Würfelt neu"""
    if game['rolls_left'] <= 0 or game['game_over']:
        return
    
    roll_dice()
    game['rolls_left'] -= 1
    update_display()

def on_hold_dice(index):
    """Erstellt Handler für Hold-Buttons"""
    def handler(trigger):
        toggle_hold(index)
        update_display()
    return handler

def on_category(category):
    """Erstellt Handler für Kategorie-Auswahl"""
    def handler(trigger):
        if game['rolls_left'] < 3:
            options = get_score_options()
            if category in options:
                game['scores'][category] = options[category]
                game['round'] += 1
                game['dice_held'] = [False] * 5
                game['rolls_left'] = 3
                
                if game['round'] > 13:
                    total = sum(game['scores'].values())
                    game['game_over'] = True
                    ui.set('message', f'Spiel vorbei! Gesamt: {total} Punkte!')
                else:
                    roll_dice()
                    update_display()
    return handler

# Initial setup
ui.clear()
ui.title('🎲 Kniffel gegen den Computer')

roll_dice()
update_display()

# Dice display and hold buttons
ui.set('dice_display', '')
ui.set('rolls_left', '')

ui.button('Würfeln').on_click(on_roll)

# Scorecard
ui.set('round', '')
ui.set('scorecard', '')
ui.set('message', '')
PYTHON;

// Find or create projects
$projects = [
    [
        'name' => 'Blackjack',
        'description' => 'Spiele Blackjack gegen den Computer. Ziel: Näher an 21 als der Dealer, ohne über 21 zu gehen.',
        'code' => $blackjackCode,
    ],
    [
        'name' => 'Kniffel',
        'description' => 'Das klassische Würfelspiel Kniffel (Yahtzee) mit 13 Runden und verschiedenen Kategorien.',
        'code' => $kniffelCode,
    ],
];

$pdo->beginTransaction();

try {
    $checkStmt = $pdo->prepare('SELECT id, name FROM projects WHERE user_id = ? AND name = ? LIMIT 1');
    $insertStmt = $pdo->prepare('INSERT INTO projects (user_id, name, description, code) VALUES (?, ?, ?, ?)');
    $updateStmt = $pdo->prepare('UPDATE projects SET description = ?, code = ?, updated_at = NOW() WHERE id = ? AND user_id = ?');
    
    $results = [];
    
    foreach ($projects as $proj) {
        $checkStmt->execute([$adminId, $proj['name']]);
        $existing = $checkStmt->fetch();
        
        if ($existing) {
            $updateStmt->execute([$proj['description'], $proj['code'], (int)$existing['id'], $adminId]);
            $results[] = [
                'name' => $proj['name'],
                'mode' => 'updated',
                'id' => (int)$existing['id'],
            ];
        } else {
            $insertStmt->execute([$adminId, $proj['name'], $proj['description'], $proj['code']]);
            $results[] = [
                'name' => $proj['name'],
                'mode' => 'inserted',
                'id' => (int)$pdo->lastInsertId(),
            ];
        }
    }
    
    $pdo->commit();
    
    echo "✅ Game-Projekte für admin@pythonide.local erfolgreich erstellt!\n\n";
    foreach ($results as $r) {
        echo sprintf(
            "  [%s] Projekt #%d: %s\n",
            strtoupper($r['mode']),
            $r['id'],
            $r['name']
        );
    }
    echo "\n";
    
} catch (Throwable $e) {
    $pdo->rollBack();
    fwrite(STDERR, "❌ Fehler: " . $e->getMessage() . "\n");
    exit(1);
}
