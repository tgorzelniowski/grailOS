# GrailOS — przewodnik projektu

Polska społeczność streetwear / sneakerheadów: katalog ubrań i butów (z porównaniem sklepów),
dropy, fity (outfity), forum, profile, stories (24h), powiadomienia, newsletter.
**Vanilla PHP 8 + MariaDB (PDO)** — bez frameworka, bez composera, bez systemu budowania.
Produkcja: **https://grailos.pl** (hosting LH.pl, aplikacja w **roocie domeny** — wszystkie ścieżki
absolutne `/...`, NIE `/grailos/...`).

Komunikuj się po polsku. UI, komunikaty i komentarze w kodzie są po polsku.

## Stack i uruchomienie
- PHP 8 (lokalnie XAMPP w `c:\xampp\htdocs\grailos`), MariaDB/MySQL przez PDO.
- Brak buildu — pliki `.php` serwowane bezpośrednio. Front to czysty HTML/CSS/JS (bez frameworków JS).
- Lint składni PHP: `c:\xampp\php\php -l <plik>` (zawsze odpalaj po edycji PHP).
- Repo: `github.com/tgorzelniowski/grailos`, główna gałąź `main`.
- Scraper (Python/Playwright) w `scraper/` ma własne venv — patrz `scraper/README.md`.

## Architektura / wzorzec stron
Każda publiczna strona to osobny plik `.php` w roocie, zwykle wg schematu:
1. `require_once 'config/db.php';` — startuje sesję, bezpieczeństwo, daje `$pdo` (PDO).
2. Obsługa `POST` (jeśli jest): `csrf_verify();` → walidacja → zapytania prepared → `header('Location: ...'); exit;` (PRG).
3. **Ustaw `$meta_*` (SEO) jeśli to strona treści** (patrz niżej), potem `require_once 'includes/header.php';` → treść HTML → `require_once 'includes/footer.php';`.

Endpointy AJAX (np. `vote.php`, `post_like.php`, `comment_like.php`, `follow.php`, `story_like.php`)
zwracają JSON (`header('Content-Type: application/json')`), bez header/footer.

> WAŻNE: strony treści (item, drop, fit, profile, post) pobierają dane **przed** include header,
> żeby ustawić meta SEO i uniknąć `header('Location')` po wysłaniu HTML.

## Kluczowe pliki / katalogi
- `config/db.php` — sesja, połączenie PDO (`$pdo`), creds z env `DB_*` (fallback w pliku). `ATTR_EMULATE_PREPARES=false`. Na produkcji `display_errors` off (log only).
- `config/security.php` — `csrf_field()`, `csrf_verify()` (rotuje token), `send_security_headers()`, `validate_session()`, `check_rate_limit()`, `upload_image($file,$folder)` → `['path'=>'/assets/uploads/<folder>/...']` lub `['error'=>...]`.
- `config/mail.php` + `includes/mailer.php` — SMTP LH.pl (port 465 SSL). `send_mail()`, `mail_last_error()`. CRLF normalizowane (qmail). Test: `admin/mail_test.php`.
- `includes/header.php` — `<head>` z **dynamicznym SEO** (`$meta_title/$meta_desc/$meta_image/$meta_canonical/$meta_robots/$meta_type/$seo_jsonld`), Open Graph + Twitter, **Google tag G-88GNK9L9GG + Consent Mode v2**, navbar, dzwonek powiadomień (`$navUnread`), wyszukiwarka.
- `includes/footer.php` — stopka, newsletter, modal zgłoszeń, globalne JS (`likeComment`, `grailReport`, `grailConfirm`).
- `includes/notifications.php` — `notify($pdo,$odbiorca,$aktor,$typ,$objectId)`, `notif_unread_count()`, `notif_render()`.
- `includes/comment_likes.php` — `clike_meta()`, `clike_button()` (lajki komentarzy + klikalna liczba → lista).
- `includes/cookie_consent.php` — baner RODO; `cookieChoose()` aktualizuje Google Consent Mode.
- `includes/stories_lib.php` / `stories_bar.php` — logika i pasek stories.
- `includes/catalog_import.php` — wspólny silnik importu katalogu (dedup-merge: **EAN → SKU → marka+nazwa**).
- `admin/` — panel (gate `admin/auth.php`). Import CSV (`import_clothing.php`), feedów afiliacyjnych (`import_feed.php` — XML/CSV + mapowanie, Google Shopping `g:`), dropów (`import_drops.php`), zarządzanie userami/fitami/postami/zgłoszeniami.
- `scraper/` — Python + Playwright (URL → CSV). Patrz osobne `scraper/README.md`.
- `assets/css/style.css` — JEDEN duży plik stylów (zmienne: `--red #FF2400`, `--gray`, `--border`, `--card`, `--white`). Fonty: Bebas Neue + Barlow Condensed.
- `assets/uploads/` — uploady (gitignorowane; `.htaccess` blokuje wykonywanie skryptów). Podkatalogi: `stories/`, `fits/`, `avatars/`, `feeds/`.
- `sitemap.php` (→ `/sitemap.xml` przez rewrite w `.htaccess`), `robots.txt`.

## Konwencje
- **Zawsze prepared statements** (`$pdo->prepare(...)->execute([...])`). Nigdy interpolacja do SQL.
- **Każdy formularz POST**: `<?= csrf_field() ?>` w HTML + `csrf_verify();` na serwerze.
- Wyjście użytkownika przez `htmlspecialchars()` (lub `nl2br(htmlspecialchars())`).
- Po udanym POST: redirect (PRG), nigdy renderowanie po `header('Location')` bez `exit`.
- Ścieżki linków i assetów **absolutne** (`/fit.php`, `/assets/...`) — wymóg deploya w roocie domeny.
- Funkcje pomocnicze w includach osłaniaj `if (!function_exists(...))` (ładowane wielokrotnie).
- Funkcje renderujące rekurencyjnie (`renderReplyTree` w `fit.php`, `renderPostReply` w `post.php`) używają `global $pdo, $VIEWER_ID;`.

## Baza danych (najważniejsze tabele)
`users` (m.in. `avatar_url`, `bio`, `is_admin`, `is_banned`), `clothing` (+ `clothing_links`, `clothing_images`, `ean`/`sku`/`source_url`), `drops` (+ `drop_images`, `drop_hypes`, `drop_notifications`, `source_url`),
`fits` (+ `fit_images`, `fit_votes`, `fit_comments`, `fit_comment_replies`), `posts` (+ `post_replies`, `post_likes`, kolumny `category`/`content`),
`follows`, `stories` (+ `story_likes`, `story_views`), `wardrobe_items`, `outfits`, `reports`, `newsletter_*`,
`comment_likes` (generyczne: `target_type` ∈ {fit_comment,fit_reply,post_reply}), `notifications` (`user_id` odbiorca, `actor_id`, `type`, `object_id`, `is_read`).

**Migracje:** uruchamiane RĘCZNIE w phpMyAdmin (pliki `*_migration.sql` bywają tymczasowe). Helpery do
`comment_likes`/`notifications`/`bio` mają try/catch — brak migracji nie wywala strony, ale funkcja jest
nieaktywna. Jeśli „lajki/powiadomienia/bio nie działają" → sprawdź czy migracja przeszła.

## Funkcje społecznościowe
- **Lajki (styl FB):** posty (`post_likes`), fity (`fit_votes`), komentarze/odpowiedzi (`comment_likes`).
  Serce = toggle, klikalna liczba → `likers.php?type=post|fit|fit_comment|fit_reply|post_reply&id=` (lista osób).
- **Powiadomienia:** dzwonek w navbarze + `notifications.php`. Typy: post_like, fit_vote, fit_comment, post_reply, comment_like, reply_like, follow. Tworzone przez `notify()` w endpointach akcji (nie powiadamia o akcji na sobie).
- **Stories:** `add_story.php` (edytor na `<canvas>` — filtry, tekst, naklejki resellerskie, skalowanie/obrót, gesty pinch-zoom + obrót na mobile, kadr 9:16 jak Instagram), `story_view.php` (auto-przewijanie, lajki).
- **Profil:** `bio` (edycja w `settings.php`), avatar, fity, szafa, outfity (`view_outfit.php`), obserwowani, prywatność.
- **Rejestracja** → redirect na `/login.php?registered=1` (komunikat o sukcesie na stronie logowania).

## SEO
- `header.php` generuje per-stronę: `<title>`, `meta description`, `canonical`, Open Graph, Twitter Card.
  Strona ustawia przed include: `$meta_title` (bez „— GrailOS", dokleja się samo), `$meta_desc`,
  `$meta_image` (może być względny — zostanie zabsolutyzowany), `$meta_type`, `$meta_robots`, `$seo_jsonld`.
- `item.php` dorzuca **JSON-LD Product** (cena, EAN, marka). Listingi (catalog/drops/fits/forum) mają własne tytuły/opisy.
- `sitemap.php` (dynamiczna mapa: strony + wszystkie produkty/dropy/fity/posty/profile) pod `/sitemap.xml`.
- `robots.txt` — indeksuje treść, blokuje admin/login/settings/search; wskazuje sitemap.

## Analityka (Google Analytics G-88GNK9L9GG)
- Wczytywane w `header.php` (jeden tag na stronę).
- **Consent Mode v2 (advanced):** domyślnie wszystkie sygnały `denied` (RODO/EOG), `security_storage: granted`,
  `wait_for_update: 500`, `ads_data_redaction` + `url_passthrough`. Baner cookie (`cookie_consent.php`)
  aktualizuje zgodę: „Akceptuj wszystkie" → `granted`, „Tylko niezbędne" → `denied`. Cookie `grailos_consent`
  pamięta wybór i przywraca zgodę przy kolejnych wizytach.

## Bezpieczeństwo
CSRF na wszystkich mutacjach, prepared statements, hardening sesji + fingerprint, nagłówki bezpieczeństwa,
rate-limit logowania, hasła `password_hash` (bcrypt cost 12). Nie commituj sekretów — DB/SMTP najlepiej z env
(`DB_*`); `config/db.php` i `config/mail.php` mają fallbacki z hasłami, więc nie publikuj ich screenów.

## Deployment (LH.pl)
Aplikacja w `public_html/grailos.pl/` (root domeny). Wgrywanie = nadpisanie zmienionych plików przez FTP/panel.
`assets/uploads/` jest gitignorowane → `.htaccess` w uploadach wgrywaj ręcznie. Po zmianach w bazie: odpal
migrację w phpMyAdmin (`serwer452079_grailos`). Po zmianach SEO: sprawdź `/sitemap.xml` i dodaj sitemap w
Google Search Console.

## Czego NIE robić
- Nie dodawać frameworków / composera / buildu (projekt celowo vanilla).
- Nie używać ścieżek względnych w linkach/assetach.
- Nie masowo usuwać komentarzy w kodzie.
- Nie zmieniać `config/db.php` ani `config/mail.php` bez wyraźnej prośby (działająca konfiguracja produkcyjna).
- Nie dublować tagu Google ani nie ładować analityki z pominięciem Consent Mode.
