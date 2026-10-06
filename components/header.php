<!-- HS Pforzheim Header Component -->
<?php
/**
 * Reusable Header Component
 * Usage: include(__DIR__ . '/../components/header.php');
 * 
 * Optional parameters:
 * - $pageTitle: String to display after logo (e.g., "Python IDE")
 * - $showUser: Boolean to show user info
 * - $userInfo: Array with user data
 * - $headerActions: HTML string for right-side buttons
 */

$pageTitle = $pageTitle ?? 'Python IDE';
$showUser = $showUser ?? false;
$userInfo = $userInfo ?? [];
$headerActions = $headerActions ?? '';

$isLoggedIn = (session_status() === PHP_SESSION_ACTIVE) && !empty($_SESSION['user_id']);
$brandHref = $isLoggedIn ? '/pythonIDE/public/dashboard.php' : '/pythonIDE/public/';
?>

<header class="hspf-header" style="min-width:0;">
  <div class="hspf-header-content" style="white-space:nowrap; flex-wrap:nowrap; min-width:0;">
    <div class="hspf-header-left" style="display:flex; align-items:center; gap:12px; flex:0 0 auto; min-width:0;">
      <a href="<?= htmlspecialchars($brandHref, ENT_QUOTES, 'UTF-8') ?>" class="hspf-brand" style="display:flex; align-items:center; flex-shrink:0;">
        <span class="hspf-brand-text">HS PF</span>
      </a>
      <?php if ($pageTitle): ?>
        <div class="hspf-divider" style="flex-shrink:0;"></div>
        <span class="hspf-page-title" style="font-size:14px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:520px; display:inline-block;">
          <?= $pageTitle ?>
        </span>
      <?php endif; ?>
    </div>
    
    <div class="hspf-header-right" style="display:flex; align-items:center; justify-content:flex-end; gap:12px; flex:1 1 auto; min-width:0; white-space:nowrap; flex-wrap:nowrap;">
      <?= $headerActions ?>
      
      <div class="hspf-divider" style="flex-shrink:0;"></div>
      
      <img src="assets/logo.svg" alt="HS Pforzheim Logo" class="hspf-logo" style="margin-left:auto; height:36px; width:auto; flex-shrink:0;">
    </div>
  </div>
</header>
