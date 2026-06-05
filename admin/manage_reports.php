<?php
require_once __DIR__ . '/auth.php';

// Resolver: [label, url, exists, author_id]
function report_target(PDO $pdo, string $type, int $id): array {
    switch ($type) {
        case 'profile':
            $s = $pdo->prepare("SELECT username FROM users WHERE id=?"); $s->execute([$id]);
            $u = $s->fetchColumn();
            return $u ? ['Profil @'.$u, '/grailos/profile.php?username='.urlencode($u), true, $id] : ['Profil #'.$id, '', false, 0];
        case 'post':
            $s = $pdo->prepare("SELECT user_id FROM posts WHERE id=?"); $s->execute([$id]); $a=$s->fetchColumn();
            return $a ? ['Post #'.$id, '/grailos/post.php?id='.$id, true, (int)$a] : ['Post #'.$id, '', false, 0];
        case 'post_reply':
            $s = $pdo->prepare("SELECT post_id, user_id FROM post_replies WHERE id=?"); $s->execute([$id]); $r=$s->fetch();
            return $r ? ['Odpowiedź forum #'.$id, '/grailos/post.php?id='.$r['post_id'].'#reply-'.$id, true, (int)$r['user_id']] : ['Odpowiedź #'.$id, '', false, 0];
        case 'fit':
            $s = $pdo->prepare("SELECT user_id FROM fits WHERE id=?"); $s->execute([$id]); $a=$s->fetchColumn();
            return $a ? ['Fit #'.$id, '/grailos/fit.php?id='.$id, true, (int)$a] : ['Fit #'.$id, '', false, 0];
        case 'fit_comment':
            $s = $pdo->prepare("SELECT fit_id, user_id FROM fit_comments WHERE id=?"); $s->execute([$id]); $r=$s->fetch();
            return $r ? ['Komentarz fitu #'.$id, '/grailos/fit.php?id='.$r['fit_id'].'#comment-'.$id, true, (int)$r['user_id']] : ['Komentarz #'.$id, '', false, 0];
        case 'fit_reply':
            $s = $pdo->prepare("SELECT fc.fit_id, fcr.user_id FROM fit_comment_replies fcr JOIN fit_comments fc ON fcr.comment_id=fc.id WHERE fcr.id=?");
            $s->execute([$id]); $r=$s->fetch();
            return $r ? ['Odpowiedź fitu #'.$id, '/grailos/fit.php?id='.$r['fit_id'], true, (int)$r['user_id']] : ['Odpowiedź fitu #'.$id, '', false, 0];
        case 'story':
            $s = $pdo->prepare("SELECT user_id FROM stories WHERE id=?"); $s->execute([$id]); $a=$s->fetchColumn();
            return $a ? ['Storka #'.$id, '/grailos/story_view.php?user='.$a, true, (int)$a] : ['Storka #'.$id.' (wygasła)', '', false, 0];
    }
    return [$type.' #'.$id, '', false, 0];
}

// --- Akcje ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $rid = (int)($_POST['report_id'] ?? 0);

    if (isset($_POST['resolve']) && $rid) {
        $pdo->prepare("UPDATE reports SET status='resolved', resolved_by=?, resolved_at=NOW() WHERE id=?")->execute([$_SESSION['user_id'], $rid]);
    } elseif (isset($_POST['dismiss']) && $rid) {
        $pdo->prepare("UPDATE reports SET status='dismissed', resolved_by=?, resolved_at=NOW() WHERE id=?")->execute([$_SESSION['user_id'], $rid]);
    } elseif (isset($_POST['reopen']) && $rid) {
        $pdo->prepare("UPDATE reports SET status='open', resolved_by=NULL, resolved_at=NULL WHERE id=?")->execute([$rid]);
    } elseif (isset($_POST['delete_report']) && $rid) {
        $pdo->prepare("DELETE FROM reports WHERE id=?")->execute([$rid]);
    } elseif (isset($_POST['clear_closed'])) {
        $pdo->query("DELETE FROM reports WHERE status IN ('resolved','dismissed')");
    } elseif (isset($_POST['delete_content']) && $rid) {
        $type = $_POST['ctype'] ?? ''; $cid = (int)($_POST['cid'] ?? 0);
        $map = [
            'post'=>'DELETE FROM posts WHERE id=?', 'post_reply'=>'DELETE FROM post_replies WHERE id=?',
            'fit'=>'DELETE FROM fits WHERE id=?', 'fit_comment'=>'DELETE FROM fit_comments WHERE id=?',
            'fit_reply'=>'DELETE FROM fit_comment_replies WHERE id=?', 'story'=>'DELETE FROM stories WHERE id=?',
        ];
        if (isset($map[$type]) && $cid) {
            $pdo->prepare($map[$type])->execute([$cid]);
            $pdo->prepare("UPDATE reports SET status='resolved', resolved_by=?, resolved_at=NOW() WHERE id=?")->execute([$_SESSION['user_id'], $rid]);
        }
    } elseif (isset($_POST['ban_author']) && $rid) {
        $aid = (int)($_POST['author_id'] ?? 0);
        if ($aid) {
            $pdo->prepare("UPDATE users SET is_banned=1 WHERE id=? AND is_admin=0")->execute([$aid]);
            $pdo->prepare("UPDATE reports SET status='resolved', resolved_by=?, resolved_at=NOW() WHERE id=?")->execute([$_SESSION['user_id'], $rid]);
        }
    }
    $qs = http_build_query(array_filter(['status'=>$_GET['status']??'', 'type'=>$_GET['type']??'']));
    header('Location: /grailos/admin/manage_reports.php' . ($qs ? '?'.$qs : '')); exit;
}

// --- Filtry ---
$f_status = $_GET['status'] ?? '';
$f_type   = $_GET['type'] ?? '';
$valid_types = ['profile','post','post_reply','fit','fit_comment','fit_reply','story'];

$cond = []; $params = [];
if (in_array($f_status, ['open','resolved','dismissed'], true)) { $cond[]='r.status=?'; $params[]=$f_status; }
if (in_array($f_type, $valid_types, true)) { $cond[]='r.content_type=?'; $params[]=$f_type; }
$where = $cond ? 'WHERE '.implode(' AND ', $cond) : '';

$open_count   = (int)$pdo->query("SELECT COUNT(*) FROM reports WHERE status='open'")->fetchColumn();
$closed_count = (int)$pdo->query("SELECT COUNT(*) FROM reports WHERE status IN ('resolved','dismissed')")->fetchColumn();

$rstmt = $pdo->prepare("
    SELECT r.*, u.username AS reporter
    FROM reports r
    LEFT JOIN users u ON r.reporter_id = u.id
    $where
    ORDER BY (r.status='open') DESC, r.created_at DESC
");
$rstmt->execute($params);
$reports = $rstmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<section class="page-header">
  <div class="page-header-tag">/// Admin · Moderacja</div>
  <h1 class="page-title">ZGŁOSZENIA</h1>
  <p class="page-sub"><?= $open_count ?> otwartych · <?= $closed_count ?> zamkniętych</p>
</section>

<!-- FILTRY -->
<div style="padding:18px 40px; border-bottom:1px solid var(--border); display:flex; gap:12px; align-items:center; flex-wrap:wrap;">
  <form method="GET" style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
    <select name="status" class="form-input" style="max-width:170px; padding:8px 0;" onchange="this.form.submit()">
      <option value="">Wszystkie statusy</option>
      <option value="open"      <?= $f_status==='open'?'selected':'' ?>>Otwarte</option>
      <option value="resolved"  <?= $f_status==='resolved'?'selected':'' ?>>Rozwiązane</option>
      <option value="dismissed" <?= $f_status==='dismissed'?'selected':'' ?>>Odrzucone</option>
    </select>
    <select name="type" class="form-input" style="max-width:190px; padding:8px 0;" onchange="this.form.submit()">
      <option value="">Wszystkie typy</option>
      <?php foreach ([
        'profile'=>'Profile','post'=>'Posty forum','post_reply'=>'Odpowiedzi forum',
        'fit'=>'Fity','fit_comment'=>'Komentarze fitów','fit_reply'=>'Odpowiedzi fitów','story'=>'Storki'
      ] as $k=>$lbl): ?>
        <option value="<?= $k ?>" <?= $f_type===$k?'selected':'' ?>><?= $lbl ?></option>
      <?php endforeach; ?>
    </select>
    <?php if ($f_status || $f_type): ?>
      <a href="/grailos/admin/manage_reports.php" class="btn btn-outline" style="font-size:11px; padding:8px 14px;">Reset ×</a>
    <?php endif; ?>
  </form>
  <?php if ($closed_count > 0): ?>
    <form method="POST" style="margin-left:auto;"><?= csrf_field() ?>
      <button type="button" class="btn-delete" style="font-size:11px; padding:8px 14px;"
              onclick="grailConfirm('Usunąć wszystkie zamknięte zgłoszenia (<?= $closed_count ?>)?', () => { const f=this.closest('form'); const i=document.createElement('input'); i.type='hidden'; i.name='clear_closed'; i.value='1'; f.appendChild(i); f.submit(); })">
        Wyczyść zamknięte (<?= $closed_count ?>)
      </button>
    </form>
  <?php endif; ?>
</div>

<div class="manage-table-wrap">
  <table class="manage-table">
    <thead>
      <tr><th>Status</th><th>Treść</th><th>Powód</th><th>Szczegóły</th><th>Zgłaszający</th><th>Data</th><th>Akcje</th></tr>
    </thead>
    <tbody>
      <?php if ($reports): foreach ($reports as $r):
        [$label, $url, $exists, $author_id] = report_target($pdo, $r['content_type'], (int)$r['content_id']);
      ?>
        <tr>
          <td><span class="report-status report-<?= $r['status'] ?>"><?= $r['status'] ?></span></td>
          <td style="max-width:200px;">
            <?php if ($url): ?>
              <a href="<?= $url ?>" target="_blank" style="color:var(--red); text-decoration:none;"><?= htmlspecialchars($label) ?></a>
            <?php else: ?><span style="color:#666;"><?= htmlspecialchars($label) ?></span><?php endif; ?>
          </td>
          <td style="font-size:12px;"><?= htmlspecialchars($r['reason']) ?></td>
          <td style="max-width:220px; color:var(--gray); font-size:12px;"><?= $r['details'] ? nl2br(htmlspecialchars(mb_substr($r['details'],0,160))) : '—' ?></td>
          <td style="font-size:12px;">@<?= htmlspecialchars($r['reporter'] ?? '—') ?></td>
          <td style="color:var(--gray); font-size:11px;"><?= date('d M Y, H:i', strtotime($r['created_at'])) ?></td>
          <td>
            <div style="display:flex; gap:6px; flex-wrap:wrap; align-items:center;">
              <?php if ($r['status'] === 'open'): ?>
                <form method="POST"><?= csrf_field() ?><input type="hidden" name="report_id" value="<?= $r['id'] ?>">
                  <button type="submit" name="resolve" class="btn" style="font-size:10px; padding:5px 10px;">Rozwiąż</button></form>
                <form method="POST"><?= csrf_field() ?><input type="hidden" name="report_id" value="<?= $r['id'] ?>">
                  <button type="submit" name="dismiss" class="btn btn-outline" style="font-size:10px; padding:5px 10px;">Odrzuć</button></form>
                <?php if ($exists && $r['content_type'] !== 'profile'): ?>
                  <form method="POST"><?= csrf_field() ?>
                    <input type="hidden" name="report_id" value="<?= $r['id'] ?>">
                    <input type="hidden" name="ctype" value="<?= htmlspecialchars($r['content_type']) ?>">
                    <input type="hidden" name="cid" value="<?= (int)$r['content_id'] ?>">
                    <button type="button" class="btn-delete" style="font-size:10px; padding:5px 10px;"
                            onclick="grailConfirm('Usunąć zgłoszoną treść?', () => { const f=this.closest('form'); const i=document.createElement('input'); i.type='hidden'; i.name='delete_content'; i.value='1'; f.appendChild(i); f.submit(); })">Usuń treść</button>
                  </form>
                <?php endif; ?>
                <?php if ($author_id): ?>
                  <form method="POST"><?= csrf_field() ?>
                    <input type="hidden" name="report_id" value="<?= $r['id'] ?>">
                    <input type="hidden" name="author_id" value="<?= $author_id ?>">
                    <button type="button" class="btn-delete" style="font-size:10px; padding:5px 10px;"
                            onclick="grailConfirm('Zbanować autora?', () => { const f=this.closest('form'); const i=document.createElement('input'); i.type='hidden'; i.name='ban_author'; i.value='1'; f.appendChild(i); f.submit(); })">Ban autora</button>
                  </form>
                <?php endif; ?>
              <?php else: ?>
                <form method="POST"><?= csrf_field() ?><input type="hidden" name="report_id" value="<?= $r['id'] ?>">
                  <button type="submit" name="reopen" class="btn btn-outline" style="font-size:10px; padding:5px 10px;">Otwórz ponownie</button></form>
                <form method="POST"><?= csrf_field() ?><input type="hidden" name="report_id" value="<?= $r['id'] ?>">
                  <button type="button" class="post-delete-btn" style="font-size:14px;"
                          onclick="grailConfirm('Usunąć ten ticket?', () => { const f=this.closest('form'); const i=document.createElement('input'); i.type='hidden'; i.name='delete_report'; i.value='1'; f.appendChild(i); f.submit(); })">✕</button></form>
              <?php endif; ?>
            </div>
          </td>
        </tr>
      <?php endforeach; else: ?>
        <tr><td colspan="7" style="padding:60px; text-align:center; color:#444; letter-spacing:3px; text-transform:uppercase; font-size:12px;">Brak zgłoszeń</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
