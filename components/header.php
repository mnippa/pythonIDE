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
?>

<header class="hspf-header">
  <div class="hspf-header-content">
    <div class="hspf-header-left">
      <a href="/" class="hspf-brand">
        <span class="hspf-brand-text">HS PF</span>
      </a>
      <?php if ($pageTitle): ?>
        <div class="hspf-divider"></div>
        <span class="hspf-page-title"><?= htmlspecialchars($pageTitle) ?></span>
      <?php endif; ?>
    </div>
    
    <div class="hspf-header-right">
      <?php if ($showUser && isset($userInfo['name'])): ?>
        <div style="display: flex; align-items: center; gap: 12px; font-size: 14px; color: var(--hspf-text-secondary);">
          <span><?= htmlspecialchars($userInfo['name']) ?></span>
          <?php if (isset($userInfo['role']) && $userInfo['role'] === 'admin'): ?>
            <span class="hspf-badge hspf-badge-primary">Admin</span>
          <?php endif; ?>
        </div>
      <?php endif; ?>
      
      <?= $headerActions ?>
      
      <div class="hspf-divider"></div>
      
      <img src="assets/logo.svg" alt="HS Pforzheim Logo" class="hspf-logo" style="margin-left: auto;">
    </div>
  </div>
</header>
