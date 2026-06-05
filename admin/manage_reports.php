<?php
require_once __DIR__ . '/auth.php';

// --- Akcje ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $rid = (int)($_POST['report_id'] ?? 0);

    if (isset($_POST['resolve']) && $rid) {
        $pdo->prepare("UPDATE reports SET status='resolved', resolved_by=?, resolved_at=NOW() WHERE id=?")
            ->execute([$_SESSION['user_id'], $rid]);
    } elseif (isset($_POST['dismiss']) && $rid) {
        $pdo->prepare("UPDATE reports SET status='dismissed', resolved_by=?, resolved_at=NOW() WHERE id=?")
            ->execute([$_SESSION['user_id'], $rid]);
    } elseif (isset($_POST['reopen']) && $rid) {
        $pdo->prepare("UPDATE reports SET status='open', resolved_by=NULL, resolved_at=NULL WHERE id=?")
            ->execute([$rid]);
    } elseif (isset($_POST['delete_content']) && $rid) {
        $type = $_POST['ctype'] ?? '';
        $cid  = (int)($_POST['cid'] ?? 0);
        $map  = [
            'post'        => 'DELETE FROM posts WHERE id=?',
            'post_reply'  => 'DELETE FROM post_replies WHERE id=?',
            'fit'         => 'DELETE FROM fits WHERE id=?',
            'fit_comment' => 'DELETE FROM fit_comments WHERE id=?',
            'fit_reply'   => 'DELETE FROM fit_comment_replies WHERE id=?',
            'story'       => 'DELETE FROM stories WHERE id=?',
        ];
        if (isset($map[$type]) && $cid) {
            $pdo->prepare($map[$type])->execute([$cid]);
            $pdo->prepare("UPDATE reports SET status='resolved', resolved_by=?, resolved_at=NOW() WHERE id=?")
                ->execute([$_SESSION['user_id'], $rid]);
        }
    }
    header('Location: /grailos/admin/manage_reports.php'); exit;
}

// Resolver: zwraca [label, url, exists] dla treści
function report_target(PDO $pdo, string $type, int $id): array {
    switch ($type) {
        case 'profile':
            $s = $pdo->prepare("SELECT username FROM users WHERE id=?"); $s->execute([$id]);
            $u = $s->fetchColumn();
            return $u ? ['Profil @'.$u, '/grailos/profile.php?username='.urlencode($u), true] : ['Profil #'.$id, '', false];
        case 'post':
            $s = $pdo->prepare("SELECT id FROM posts WHERE id=?"); $s->execute([$id]);
            return $s->fetchColumn() ? ['Post #'.$id, '/grailos/post.php?id='.$id, true] : ['Post #'.$id, '', false];
        case 'post_reply':
            $s = $pdo->prepare("SELECT post_id FROM post_replies WHERE id=?"); $s->execute([$id]);
            $p = $s->fetchColumn();
            return $p ? ['Odpowiedź forum #'.$id, '/grailos/post.php?id='.$p.'#reply-'.$id, true] : ['Odpowiedź #'.$id, '', false];
        case 'fit':
            $s = $pdo->prepare("SELECT id FROM fits WHERE id=?"); $s->execute([$id]);
            return $s->fetchColumn() ? ['Fit #'.$id, '/grailos/fit.php?id='.$id, true] : ['Fit #'.$id, '', false];
        case 'fit_comment':
            $s = $pdo->prepare("SELECT fit_id FROM fit_comments WHERE id=?"); $s->execute([$id]);
            $f = $s->fetchColumn();
            return $f ? ['Komentarz fitu #'.$id, '/grailos/fit.php?id='.$f.'#comment-'.$id, true] : ['Komentarz #'.$id, '', false];
        case 'fit_reply':
            $s = $pdo->prepare("SELECT fc.fit_id FROM fit_comment_replies fcr JOIN fit_comments fc ON fcr.comment_id=fc.id WHERE fcr.id=?");
            $s->execute([$id]); $f = $s->fetchColumn();
            return $f ? ['Odpowiedź fitu #'.$id, '/grailos/fit.php?id='.$f, true] : ['Odpowiedź fitu #'.$id, '', false];
        case 'story':
            $s = $pdo->prepare("SELECT user_id FROM stories WHERE id=?"); $s->execute([$id]);
            $a = $s->fetchColumn();
            return $a ? ['Storka #'.$id, '/grailos/story_view.php?user='.$a, true] : ['Storka #'.$id.' (wygasła/usunięta)', '', false];
    }
    return [$type.' #'.$id, '', false];
}

$open_count = (int)$pdo->query("SELECT COUNT(*) FROM reports WHERE status='open'")->fetchColumn();
$reports = $pdo->query("
    SELECT r.*, u.username AS reporter
    FROM reports r
    LEFT JOIN users u ON r.reporter_id = u.id
    ORDER BY (r.status='open') DESC, r.created_at DESC
")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<section class="page-header">
  <div class="page-header-tag">/// Admin · Moderacja</div>
  <h1 class="page-title">ZGŁOSZENIA</h1>
  <p class="page-sub"><?= $open_count ?> otwartych · <?= count($reports) ?> łącznie</p>
</section>

<div class="manage-table-wrap">
  <table class="manage-table">
    <thead>
      <tr>
        <th>Status</th><th>Treść</th><th>Powód</th><th>Szczegóły</th>
        <th>Zgłaszający</th><th>Data</th><th>Akcje</th>
      </tr>
    </thead>
    <tbody>
      <?php if ($reports): foreach ($reports as $r):
        [$label, $url, $exists] = report_target($pdo, $r['content_type'], (int)$r['content_id']);
      ?>
        <tr>
          <td><span class="report-status report-<?= $r['status'] ?>"><?= $r['status'] ?></span></td>
          <td style="max-width:200px;">
            <?php if ($url): ?>
              <a href="<?= $url ?>" target="_blank" style="color:var(--red); text-decoration:none;"><?= htmlspecialchars($label) ?></a>
            <?php else: ?>
              <span style="color:#666;"><?= htmlspecialchars($label) ?></span>
            <?php endif; ?>
          </td>
          <td style="font-size:12px;"><?= htmlspecialchars($r['reason']) ?></td>
          <td style="max-width:220px; color:var(--gray); font-size:12px;">
            <?= $r['details'] ? nl2br(htmlspecialchars(mb_substr($r['details'],0,160))) : '—' ?>
          </td>
          <td style="font-size:12px;">@<?= htmlspecialchars($r['reporter'] ?? '—') ?></td>
          <td style="color:var(--gray); font-size:11px;"><?= date('d M Y, H:i', strtotime($r['created_at'])) ?></td>
          <td>
            <div style="display:flex; gap:6px; flex-wrap:wrap; align-items:center;">
              <?php if ($r['status'] === 'open'): ?>
                <form method="POST"><?= csrf_field() ?>
                  <input type="hidden" name="report_id" value="<?= $r['id'] ?>">
                  <button type="submit" name="resolve" class="btn" style="font-size:10px; padding:5px 10px;">Rozwiąż</button>
                </form>
                <form method="POST"><?= csrf_field() ?>
                  <input type="hidden" name="report_id" value="<?= $r['id'] ?>">
                  <button type="submit" name="dismiss" class="btn btn-outline" style="font-size:10px; padding:5px 10px;">Odrzuć</button>
                </form>
                <?php if ($exists && $r['content_type'] !== 'profile'): ?>
                  <form method="POST"><?= csrf_field() ?>
                    <input type="hidden" name="report_id" value="<?= $r['id'] ?>">
                    <input type="hidden" name="ctype" value="<?= htmlspecialchars($r['content_type']) ?>">
                    <input type="hidden" name="cid" value="<?= (int)$r['content_id'] ?>">
                    <button type="button" name="delete_content" class="btn-delete" style="font-size:10px; padding:5px 10px;"
                            onclick="grailConfirm('Usunąć zgłoszoną treść?', () => { const b=this; const f=b.closest('form'); const i=document.createElement('input'); i.type='hidden'; i.name='delete_content'; i.value='1'; f.appendChild(i); f.submit(); })">Usuń treść</button>
                  </form>
                <?php endif; ?>
              <?php else: ?>
                <form method="POST"><?= csrf_field() ?>
                  <input type="hidden" name="report_id" value="<?= $r['id'] ?>">
                  <button type="submit" name="reopen" class="btn btn-outline" style="font-size:10px; padding:5px 10px;">Otwórz ponownie</button>
                </form>
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
