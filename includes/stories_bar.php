<?php
// Pasek storek — widoczny tylko dla zalogowanych.
// Pokazuje autorów których obserwuję (+ mnie) mających storkę z ostatnich 24h.
if (isset($_SESSION['user_id'])):
    $me = (int)$_SESSION['user_id'];

    $sb_stmt = $pdo->prepare("
        SELECT s.user_id,
               u.username,
               u.avatar_url,
               (s.user_id = ?) AS is_me,
               MAX(s.created_at) AS latest,
               SUM(CASE WHEN sv.id IS NULL THEN 1 ELSE 0 END) AS unseen_count
        FROM stories s
        JOIN users u ON s.user_id = u.id
        LEFT JOIN story_views sv ON sv.story_id = s.id AND sv.user_id = ?
        WHERE s.created_at > NOW() - INTERVAL 24 HOUR
          AND (s.user_id = ?
               OR s.user_id IN (SELECT following_id FROM follows WHERE follower_id = ?))
        GROUP BY s.user_id, u.username, u.avatar_url
        ORDER BY is_me DESC, unseen_count DESC, latest DESC
    ");
    try {
        $sb_stmt->execute([$me, $me, $me, $me]);
        $sb_stories = $sb_stmt->fetchAll();
    } catch (Exception $e) {
        $sb_stories = []; // tabele jeszcze nie zmigrowane
    }
?>
<div class="stories-bar">
  <!-- Dodaj storkę -->
  <a href="/add_story.php" class="story-item story-add">
    <div class="story-ring story-add-ring">
      <?php if (!empty($_SESSION['avatar_url'])): ?>
        <img src="<?= htmlspecialchars($_SESSION['avatar_url']) ?>" alt="">
      <?php else: ?>
        <span class="story-init"><?= strtoupper(htmlspecialchars($_SESSION['username'][0])) ?></span>
      <?php endif; ?>
      <span class="story-add-plus">+</span>
    </div>
    <span class="story-name">Dodaj</span>
  </a>

  <?php
    // Najpierw własna aktywna storka (zaraz po „Dodaj”), potem obserwowani
    foreach ($sb_stories as $st):
      if (!$st['is_me']) continue;
  ?>
    <a href="/story_view.php?user=<?= (int)$st['user_id'] ?>" class="story-item">
      <div class="story-ring seen">
        <?php if ($st['avatar_url']): ?>
          <img src="<?= htmlspecialchars($st['avatar_url']) ?>" alt="">
        <?php else: ?>
          <span class="story-init"><?= strtoupper(htmlspecialchars($st['username'][0])) ?></span>
        <?php endif; ?>
      </div>
      <span class="story-name">Twoja</span>
    </a>
  <?php endforeach; ?>

  <?php foreach ($sb_stories as $st):
    if ($st['is_me']) continue;
  ?>
    <a href="/story_view.php?user=<?= (int)$st['user_id'] ?>" class="story-item">
      <div class="story-ring <?= $st['unseen_count'] > 0 ? 'unseen' : 'seen' ?>">
        <?php if ($st['avatar_url']): ?>
          <img src="<?= htmlspecialchars($st['avatar_url']) ?>" alt="">
        <?php else: ?>
          <span class="story-init"><?= strtoupper(htmlspecialchars($st['username'][0])) ?></span>
        <?php endif; ?>
      </div>
      <span class="story-name">@<?= htmlspecialchars($st['username']) ?></span>
    </a>
  <?php endforeach; ?>
</div>
<?php endif; ?>
