<?php
$conn = new mysqli('localhost', 'root', 'start123', 'pythonide');
$conn->set_charset('utf8mb4');

function updateProjectFileByName(mysqli $conn, string $projectName, string $fileName, string $content): void {
    $projectStmt = $conn->prepare('SELECT id FROM projects WHERE name = ? ORDER BY id DESC LIMIT 1');
    $projectStmt->bind_param('s', $projectName);
    $projectStmt->execute();
    $project = $projectStmt->get_result()->fetch_assoc();

    if (!$project) {
        echo "✗ Project not found: {$projectName}\n";
        return;
    }

    $projectId = (int)$project['id'];
    $updateStmt = $conn->prepare('UPDATE project_files SET content = ?, updated_at = NOW() WHERE project_id = ? AND name = ?');
    $updateStmt->bind_param('sis', $content, $projectId, $fileName);

    if ($updateStmt->execute() && $updateStmt->affected_rows >= 0) {
        echo "✓ Updated {$fileName} in project {$projectName} (ID: {$projectId})\n";
    } else {
        echo "✗ Failed to update {$fileName} in project {$projectName}: {$conn->error}\n";
    }
}

$blackjackInit = <<<'PY'
import idegui as ui
import random

# Persistent game state in module globals
if 'GAME_STATE' not in globals() or not isinstance(globals().get('GAME_STATE'), dict):
    GAME_STATE = {
        'deck': [],
        'player': [],
        'dealer': [],
        'game_over': True
    }

def create_deck():
    cards = ['2', '3', '4', '5', '6', '7', '8', '9', '10', 'J', 'Q', 'K', 'A']
    deck = cards * 4
    random.shuffle(deck)
    return deck

def deal_card(deck):
    if not deck:
        deck.extend(create_deck())
    return deck.pop()

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
    return ' '.join(str(c) for c in hand) if hand else '---'

def new_game(trigger):
    global GAME_STATE

    deck = create_deck()
    player_hand = [deal_card(deck), deal_card(deck)]
    dealer_hand = [deal_card(deck), deal_card(deck)]

    GAME_STATE = {
        'deck': deck,
        'player': player_hand,
        'dealer': dealer_hand,
        'game_over': False
    }

    ui.set('player_cards', format_hand(player_hand))
    ui.set('player_sum', str(calculate_sum(player_hand)))
    ui.set('dealer_cards', format_hand([dealer_hand[0], '?']))
    ui.set('dealer_sum', '?')
    ui.set('message', f'Spiel gestartet. Spieler: {calculate_sum(player_hand)} | Dealer zeigt: {dealer_hand[0]}')

def hit(trigger):
    global GAME_STATE

    if not isinstance(GAME_STATE, dict) or GAME_STATE.get('game_over', True):
        ui.set('message', 'Bitte erst "Neues Spiel" starten oder neues Spiel beginnen.')
        return

    GAME_STATE['player'].append(deal_card(GAME_STATE['deck']))
    player_sum = calculate_sum(GAME_STATE['player'])

    ui.set('player_cards', format_hand(GAME_STATE['player']))
    ui.set('player_sum', str(player_sum))

    if player_sum > 21:
        GAME_STATE['game_over'] = True
        ui.set('dealer_cards', format_hand(GAME_STATE['dealer']))
        ui.set('dealer_sum', str(calculate_sum(GAME_STATE['dealer'])))
        ui.set('message', f'❌ BUST! Spieler über 21 ({player_sum}) - Dealer gewinnt!')
    else:
        ui.set('message', f'Karte gezogen. Spieler-Summe: {player_sum}')

def stand(trigger):
    global GAME_STATE

    if not isinstance(GAME_STATE, dict) or GAME_STATE.get('game_over', True):
        ui.set('message', 'Bitte erst "Neues Spiel" starten oder neues Spiel beginnen.')
        return

    GAME_STATE['game_over'] = True

    while calculate_sum(GAME_STATE['dealer']) < 17:
        GAME_STATE['dealer'].append(deal_card(GAME_STATE['deck']))

    player_sum = calculate_sum(GAME_STATE['player'])
    dealer_sum = calculate_sum(GAME_STATE['dealer'])

    ui.set('dealer_cards', format_hand(GAME_STATE['dealer']))
    ui.set('dealer_sum', str(dealer_sum))

    if dealer_sum > 21:
        ui.set('message', f'✅ Dealer BUST ({dealer_sum})! Spieler gewinnt ({player_sum})')
    elif player_sum > dealer_sum:
        ui.set('message', f'✅ Spieler gewinnt! {player_sum} vs {dealer_sum}')
    elif player_sum < dealer_sum:
        ui.set('message', f'❌ Dealer gewinnt! {dealer_sum} vs {player_sum}')
    else:
        ui.set('message', f'🤝 Push! Unentschieden bei {player_sum}')

# Initial UI text only when no game data exists in HTML yet
if ui.get('player_cards', '').strip() in ['', '---']:
    ui.set('message', 'Willkommen zu Blackjack! Klicke "Neues Spiel"')
PY;

$kniffelInit = <<<'PY'
import idegui as ui
import random
from collections import Counter

DICE_FACE = {
    1: '⚀',
    2: '⚁',
    3: '⚂',
    4: '⚃',
    5: '⚄',
    6: '⚅'
}

if 'ROLL_COUNT' not in globals():
    ROLL_COUNT = 0

def roll(trigger):
    global ROLL_COUNT
    ROLL_COUNT += 1

    dice = [random.randint(1, 6) for _ in range(5)]
    ui.set('dice', ' '.join(DICE_FACE[d] for d in dice))

    score = sum(dice)
    ui.set('score', str(score))

    counts = Counter(dice)
    number, count = counts.most_common(1)[0]

    if count == 5:
        ui.set('message', f'🎉 KNIFFEL! 5x {number}! Punkte: {score} (Wurf #{ROLL_COUNT})')
    elif count == 4:
        ui.set('message', f'✅ Viererpasch! 4x {number}. Punkte: {score} (Wurf #{ROLL_COUNT})')
    elif count == 3:
        ui.set('message', f'👍 Dreierpasch! 3x {number}. Punkte: {score} (Wurf #{ROLL_COUNT})')
    else:
        ui.set('message', f'Wurf #{ROLL_COUNT}: {dice} | Punkte: {score}')

if ui.get('dice', '').strip() in ['', '⚪ ⚪ ⚪ ⚪ ⚪']:
    ui.set('dice', '⚀ ⚀ ⚀ ⚀ ⚀')
if ui.get('score', '').strip() == '':
    ui.set('score', '0')
if ui.get('message', '').strip() == '':
    ui.set('message', 'Willkommen zu Kniffel! Klicke "Würfeln"')
PY;

echo "Applying runtime fixes...\n";
updateProjectFileByName($conn, 'Blackjack Card Game', 'init.py', $blackjackInit);
updateProjectFileByName($conn, 'Kniffel (Yahtzee)', 'init.py', $kniffelInit);
echo "Done.\n";
