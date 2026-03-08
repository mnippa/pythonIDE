<?php
$conn = new mysqli('localhost', 'root', 'start123', 'pythonide');
$conn->set_charset('utf8mb4');

// Get user Markus2
$stmt = $conn->prepare('SELECT id FROM users WHERE email = ?');
$email = 'markus2@example.com';
$stmt->bind_param('s', $email);
$stmt->execute();
$userResult = $stmt->get_result();
$user = $userResult->fetch_assoc();
$userId = $user['id'];

echo "Creating Blackjack and Kniffel projects for Markus2 (ID: $userId)\n\n";

// ============ BLACKJACK ============

echo "=== Creating Blackjack Project ===\n";

$blackjackHtml = <<<'HTML'
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Blackjack</title>
</head>
<body>
    <div class="game-container">
        <h1>♠️ Blackjack ♠️</h1>
        
        <div class="section">
            <h2>Dealer</h2>
            <div class="hand" data-element="dealer_cards">---</div>
            <p>Summe: <span data-element="dealer_sum">0</span></p>
        </div>
        
        <div class="section">
            <h2>Spieler</h2>
            <div class="hand" data-element="player_cards">---</div>
            <p>Summe: <span data-element="player_sum">0</span></p>
        </div>
        
        <div class="buttons">
            <button class="btn" data-run-python="true" data-run-name="new_game">🎰 Neues Spiel</button>
            <button class="btn" data-run-python="true" data-run-name="hit">✋ Hit</button>
            <button class="btn" data-run-python="true" data-run-name="stand">✋ Stand</button>
        </div>
        
        <div class="result" data-element="message">Klicke "Neues Spiel" zum Starten</div>
    </div>
</body>
</html>
HTML;

$blackjackCss = <<<'CSS'
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Arial', sans-serif;
    background: linear-gradient(135deg, #1a472a 0%, #2d5a3d 100%);
    min-height: 100vh;
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 20px;
}

.game-container {
    background: #0d3d1f;
    border: 3px solid #ffd700;
    border-radius: 15px;
    padding: 30px;
    max-width: 600px;
    color: #fff;
}

h1 {
    text-align: center;
    margin-bottom: 30px;
    color: #ffd700;
    font-size: 32px;
}

.section {
    margin: 20px 0;
    padding: 15px;
    border: 2px solid #ffd700;
    border-radius: 8px;
    background: rgba(255, 215, 0, 0.05);
}

.section h2 {
    color: #ffd700;
    margin-bottom: 10px;
    font-size: 18px;
}

.hand {
    background: #1a472a;
    padding: 15px;
    border-radius: 5px;
    margin-bottom: 10px;
    font-size: 18px;
    font-weight: bold;
    font-family: monospace;
}

.buttons {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 10px;
    margin: 30px 0;
}

.btn {
    padding: 12px;
    background: linear-gradient(135deg, #ffd700 0%, #ffed4e 100%);
    color: #000;
    border: none;
    border-radius: 8px;
    font-weight: bold;
    cursor: pointer;
    transition: transform 0.2s;
}

.btn:hover {
    transform: scale(1.05);
}

.result {
    padding: 15px;
    background: rgba(255, 215, 0, 0.1);
    border: 2px solid #ffd700;
    border-radius: 8px;
    text-align: center;
    font-weight: bold;
    min-height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
}
CSS;

$blackjackPy = <<<'PY'
import idegui as ui
import random

def new_game(trigger):
    """Neues Blackjack-Spiel starten"""
    deck = create_deck()
    player_hand = [deal_card(deck), deal_card(deck)]
    dealer_hand = [deal_card(deck), deal_card(deck)]
    
    # Speichern im HTML (hidden)
    ui.set('player_cards', format_hand(player_hand))
    ui.set('player_sum', str(calculate_sum(player_hand)))
    ui.set('dealer_cards', format_hand([dealer_hand[0], '?']))
    ui.set('message', f'Spieler: {calculate_sum(player_hand)} | Dealer zeigt: {dealer_hand[0]}')
    
    # Game state speichern in data-element (global)
    global GAME_STATE
    GAME_STATE = {
        'deck': deck,
        'player': player_hand,
        'dealer': dealer_hand,
        'game_over': False
    }

def hit(trigger):
    """Spieler zieht eine Karte"""
    if 'GAME_STATE' not in globals():
        ui.set('message', 'Bitte erst "Neues Spiel" starten!')
        return
    
    global GAME_STATE
    if GAME_STATE['game_over']:
        ui.set('message', 'Spiel vorbei! Neues Spiel starten.')
        return
    
    GAME_STATE['player'].append(deal_card(GAME_STATE['deck']))
    player_sum = calculate_sum(GAME_STATE['player'])
    
    ui.set('player_cards', format_hand(GAME_STATE['player']))
    ui.set('player_sum', str(player_sum))
    
    if player_sum > 21:
        ui.set('message', '❌ BUST! Spieler über 21 - Dealer gewinnt!')
        GAME_STATE['game_over'] = True
    else:
        ui.set('message', f'Karte gezogen! Summe: {player_sum}')

def stand(trigger):
    """Spieler möchte nicht mehr ziehen"""
    if 'GAME_STATE' not in globals():
        ui.set('message', 'Bitte erst "Neues Spiel" starten!')
        return
    
    global GAME_STATE
    GAME_STATE['game_over'] = True
    
    # Dealer zieht bis 17
    while calculate_sum(GAME_STATE['dealer']) < 17:
        GAME_STATE['dealer'].append(deal_card(GAME_STATE['deck']))
    
    player_sum = calculate_sum(GAME_STATE['player'])
    dealer_sum = calculate_sum(GAME_STATE['dealer'])
    
    ui.set('dealer_cards', format_hand(GAME_STATE['dealer']))
    ui.set('dealer_sum', str(dealer_sum))
    
    if dealer_sum > 21:
        ui.set('message', f'✅ Dealer BUST! Spieler gewinnt! ({player_sum} vs {dealer_sum})')
    elif player_sum > dealer_sum:
        ui.set('message', f'✅ Spieler gewinnt! ({player_sum} vs {dealer_sum})')
    elif player_sum < dealer_sum:
        ui.set('message', f'❌ Dealer gewinnt! ({player_sum} vs {dealer_sum})')
    else:
        ui.set('message', f'🤝 Push! Unentschieden ({player_sum})')

def create_deck():
    cards = ['2', '3', '4', '5', '6', '7', '8', '9', '10', 'J', 'Q', 'K', 'A']
    return cards * 4  # 4 Decks

def deal_card(deck):
    return deck.pop(random.randint(0, len(deck)-1))

def calculate_sum(hand):
    total = 0
    aces = 0
    for card in hand:
        if card == 'A':
            aces += 1
            total += 11
        elif card in ['J', 'Q', 'K']:
            total += 10
        else:
            total += int(card)
    
    while total > 21 and aces > 0:
        total -= 10
        aces -= 1
    
    return total

def format_hand(hand):
    return ' '.join(str(c) for c in hand)

# Initialize
GAME_STATE = None
ui.set('message', 'Willkommen zu Blackjack! Klicke "Neues Spiel"')
PY;

// Create Blackjack project
$projectName = 'Blackjack Card Game';
$projectDesc = 'Ein Blackjack-Kartenspiel mit idegui und Python';
$projectType = 'html';

$stmt = $conn->prepare('INSERT INTO projects (user_id, name, description, project_type, created_at) VALUES (?, ?, ?, ?, NOW())');
$stmt->bind_param('isss', $userId, $projectName, $projectDesc, $projectType);
$stmt->execute();
$blackjackProjectId = $conn->insert_id;

echo "✓ Blackjack project created (ID: $blackjackProjectId)\n";

// Insert Blackjack files
$files = [
    'index.html' => $blackjackHtml,
    'style.css' => $blackjackCss,
    'init.py' => $blackjackPy
];

foreach ($files as $fileName => $content) {
    $escapedContent = $conn->real_escape_string($content);
    $sql = "INSERT INTO project_files (project_id, folder_id, name, content, created_at) VALUES ($blackjackProjectId, NULL, '$fileName', '$escapedContent', NOW())";
    $conn->query($sql);
    echo "  ✓ $fileName\n";
}

// ============ KNIFFEL (YAHTZEE) ============

echo "\n=== Creating Kniffel (Yahtzee) Project ===\n";

$kniffelHtml = <<<'HTML'
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Kniffel</title>
</head>
<body>
    <div class="game-container">
        <h1>🎲 Kniffel 🎲</h1>
        
        <div class="dice-section">
            <h2>Würfel</h2>
            <div class="dice-display" data-element="dice">⚪ ⚪ ⚪ ⚪ ⚪</div>
            <div class="selected" data-element="selected_dice">Keine Würfel ausgewählt</div>
        </div>
        
        <div class="buttons">
            <button class="btn large" data-function="roll_dice" name="roll_dice" value="2">🎲 Würfeln</button>
            <button class="btn" data-function="reset" name="reset" value="0">🔄 Reset</button>
        </div>
        
        <div class="scoring-section">
            <h2>Punkte</h2>
            <div class="score" data-element="score">0</div>
        </div>
        
        <div class="message" data-element="message">Klicke auf "Würfeln" zum Starten</div>
    </div>
</body>
</html>
HTML;

$kniffelCss = <<<'CSS'
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Arial', sans-serif;
    background: linear-gradient(135deg, #4a3f83 0%, #2a2a5a 100%);
    min-height: 100vh;
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 20px;
}

.game-container {
    background: #3a3a5a;
    border: 3px solid #ff6b9d;
    border-radius: 15px;
    padding: 30px;
    max-width: 600px;
    color: #fff;
}

h1 {
    text-align: center;
    margin-bottom: 30px;
    color: #ff6b9d;
    font-size: 32px;
}

h2 {
    color: #ff6b9d;
    margin-bottom: 15px;
    font-size: 18px;
}

.dice-section, .scoring-section {
    margin: 20px 0;
    padding: 15px;
    border: 2px solid #ff6b9d;
    border-radius: 8px;
    background: rgba(255, 107, 157, 0.05);
}

.dice-display {
    font-size: 48px;
    text-align: center;
    padding: 20px;
    background: rgba(0, 0, 0, 0.3);
    border-radius: 5px;
    margin-bottom: 10px;
}

.selected {
    color: #ffcc00;
    font-size: 14px;
    text-align: center;
    padding: 8px;
}

.buttons {
    text-align: center;
    margin: 30px 0;
}

.btn {
    padding: 12px 24px;
    background: linear-gradient(135deg, #ff6b9d 0%, #ff8fb3 100%);
    color: #fff;
    border: none;
    border-radius: 8px;
    font-weight: bold;
    cursor: pointer;
    margin: 5px;
    transition: transform 0.2s;
}

.btn.large {
    font-size: 18px;
    padding: 15px 40px;
}

.btn:hover {
    transform: scale(1.05);
}

.score {
    font-size: 48px;
    font-weight: bold;
    color: #ffcc00;
    text-align: center;
    padding: 20px;
    background: rgba(0, 0, 0, 0.3);
    border-radius: 5px;
}

.message {
    padding: 15px;
    background: rgba(255, 107, 157, 0.1);
    border: 2px solid #ff6b9d;
    border-radius: 8px;
    text-align: center;
    font-weight: bold;
    min-height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
}
CSS;

$kniffelPy = <<<'PY'
import idegui as ui
import random

if 'GAME' not in globals():
    GAME = {'score': 0, 'rolls': 0, 'dice': [1, 1, 1, 1, 1]}

def roll_dice(trigger):
    """Würfelt (trigger.value enthält Anzahl der Würfel: 2, 3, 4, oder 5)"""
    global GAME
    num_dice = int(trigger.value) if trigger.value else 5
    
    # Würfel neu werfen
    GAME['dice'] = [random.randint(1, 6) for _ in range(5)]
    GAME['rolls'] += 1
    
    # Würfel anzeigen
    dice_str = ' '.join(['🎲' for _ in GAME['dice']])
    ui.set('dice', dice_str)
    
    # Score berechnen
    score = sum(GAME['dice'])
    GAME['score'] += score
    ui.set('score', str(GAME['score']))
    ui.set('selected_dice', f'Würfel: {GAME["dice"]} | Summe: {score}')
    
    # Häufigste zahlen finden
    from collections import Counter
    counts = Counter(GAME['dice'])
    most_common = counts.most_common(1)
    
    if most_common:
        number, count = most_common[0]
        if count == 5:
            ui.set('message', f'🎉 KNIFFEL! 5x {number}! Gesamt: {GAME["score"]}')
        elif count == 4:
            ui.set('message', f'✅ Viererpasch! 4x {number}. Gesamt: {GAME["score"]}')
        else:
            ui.set('message', f'Würfe: {GAME["rolls"]} | Gesamt: {GAME["score"]}')

def reset(trigger):
    """Setzt das Spiel zurück (trigger.value = 0)"""
    global GAME
    GAME = {'score': 0, 'rolls': 0, 'dice': [1, 1, 1, 1, 1]}
    ui.set('dice', '⚪ ⚪ ⚪ ⚪ ⚪')
    ui.set('score', '0')
    ui.set('selected_dice', 'Keine Würfel ausgewählt')
    ui.set('message', 'Spiel zurückgesetzt! Klicke Würfeln zum Starten')

# Initialize
ui.set('message', 'Willkommen zu Kniffel! Klicke "Würfeln"')
ui.set('score', '0')
ui.set('dice', '⚪ ⚪ ⚪ ⚪ ⚪')
ui.set('selected_dice', 'Keine Würfel ausgewählt')
PY;

// Create Kniffel project
$projectName = 'Kniffel (Yahtzee)';
$projectDesc = 'Ein Kniffel-Würfelspiel mit idegui und Python';
$projectType = 'html';

$stmt = $conn->prepare('INSERT INTO projects (user_id, name, description, project_type, created_at) VALUES (?, ?, ?, ?, NOW())');
$stmt->bind_param('isss', $userId, $projectName, $projectDesc, $projectType);
$stmt->execute();
$kniffelProjectId = $conn->insert_id;

echo "✓ Kniffel project created (ID: $kniffelProjectId)\n";

// Insert Kniffel files
$files = [
    'index.html' => $kniffelHtml,
    'style.css' => $kniffelCss,
    'init.py' => $kniffelPy
];

foreach ($files as $fileName => $content) {
    $escapedContent = $conn->real_escape_string($content);
    $sql = "INSERT INTO project_files (project_id, folder_id, name, content, created_at) VALUES ($kniffelProjectId, NULL, '$fileName', '$escapedContent', NOW())";
    $conn->query($sql);
    echo "  ✓ $fileName\n";
}

echo "\n=== DONE ===\n";
echo "✅ Blackjack Project ID: $blackjackProjectId\n";
echo "✅ Kniffel Project ID: $kniffelProjectId\n";
