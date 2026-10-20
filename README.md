<div align="center">

# 🏆 GrailOS

### Polska społeczność streetwear i sneakerheadów

Katalog ubrań i butów · dropy · fity · forum · stories

[![Live](https://img.shields.io/badge/LIVE-grailos.pl-FF2400?style=for-the-badge)](https://grailos.pl)
![PHP](https://img.shields.io/badge/PHP-8-777BB4?style=for-the-badge&logo=php&logoColor=white)
![MariaDB](https://img.shields.io/badge/MariaDB-003545?style=for-the-badge&logo=mariadb&logoColor=white)
![JavaScript](https://img.shields.io/badge/JavaScript-F7DF1E?style=for-the-badge&logo=javascript&logoColor=black)
![Python](https://img.shields.io/badge/Python-3776AB?style=for-the-badge&logo=python&logoColor=white)

</div>

---

## 👋 O projekcie

**GrailOS** to samodzielnie zaprojektowana i napisana od zera platforma dla polskiej sceny streetwear i sneakerheadów. Projekt działa produkcyjnie pod adresem **[grailos.pl](https://grailos.pl)**.

Łączy w jednym miejscu katalog produktów z porównaniem sklepów, kalendarz premier oraz funkcje społecznościowe znane z popularnych serwisów, dopasowane do kultury resellerskiej.

Całość powstała **bez frameworków i bez systemu budowania**. To świadoma decyzja, żeby pokazać solidną znajomość podstaw: PHP, SQL, HTTP, bezpieczeństwa i pracy z przeglądarką.

---

## ✨ Co potrafi aplikacja

| | |
|---|---|
| 👟 **Katalog** | Ubrania i buty z porównaniem ofert z wielu sklepów oraz wyszukiwarką z podpowiedziami |
| 🔥 **Dropy** | Kalendarz premier, „hype" na dropy i powiadomienia e-mail |
| 📸 **Fity** | Outfity użytkowników z głosowaniem, komentarzami i odpowiedziami |
| 💬 **Forum** | Posty, kategorie, wątki odpowiedzi |
| ⏱️ **Stories 24h** | Edytor graficzny w przeglądarce (filtry, tekst, naklejki, gesty dotykowe, kadr 9:16) |
| 👤 **Profile** | Avatar, bio, szafa, outfity, obserwowani, ustawienia prywatności |
| 🔔 **Powiadomienia** | Dzwonek w nawigacji i osobna strona z historią aktywności |
| 🛠️ **Panel admina** | Moderacja treści, zgłoszenia, importy danych |

---

## 🧠 Wyzwania inżynierskie

Nad czym było najciekawiej pracować:

- **Import i deduplikacja danych produktowych** z różnych źródeł (CSV, feedy XML, scraping) do spójnego katalogu
- **Edytor stories w `<canvas>`** z obsługą gestów na urządzeniach mobilnych
- **System interakcji społecznościowych**: lajki, komentarze zagnieżdżone, obserwowanie i powiadomienia
- **SEO**: dynamiczne metadane, Open Graph, dane strukturalne i mapa strony
- **Prywatność i zgodność z RODO**: baner cookies zintegrowany z Google Consent Mode v2
- **Bezpieczeństwo aplikacji** budowane od podstaw, bez gotowego frameworka

---

## 🧰 Technologie

**Backend:** PHP 8 · PDO · MariaDB  
**Frontend:** HTML5 · CSS3 · JavaScript (vanilla) · Canvas API  
**Narzędzia:** Python · Playwright · Git · Apache · SMTP  
**Wdrożenie:** hosting współdzielony, domena produkcyjna, cron

---

## 🖼️ Zrzuty ekranu

💻 Desktop
<div align="center">
<img src="docs/screenshots/home.png" alt="Strona główna" width="100%" />
<sub><b>Strona główna</b></sub>
</div>

<br>

<div align="center">
<img src="docs/screenshots/catalog.png" alt="Katalog" width="49%" />
<img src="docs/screenshots/product.png" alt="Produkt" width="49%" />
</div>
<div align="center">
<sub><b>Katalog</b> – filtry po kategorii i marce</sub>
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
<sub><b>Produkt</b> – galeria i porównanie sklepów</sub>
</div>
📱 Mobile
<table align="center">
  <tr> 
    <td align="center" width="50%"> 
      <img src="docs/screenshots/homemobile.png" alt="Strona główna – mobile" width="260" />
      <br> <sub><b>Strona główna</b></sub> 
    </td> 
    <td align="center" width="50%"> 
      <img src="docs/screenshots/catalogmobile.png" alt="Katalog – mobile" width="260" />
      <br> <sub><b>Katalog</b></sub> 
    </td> 
  </tr> 
</table> 
<p align="center"><i>Pełną wersję możesz zobaczyć na żywo: <b><a href="https://grailos.pl">grailos.pl</a></b></i></p>

*Pełną wersję możesz zobaczyć na żywo: **[grailos.pl](https://grailos.pl)***

---

## 📬 Kontakt

**Tomasz Gorzelniowski** 
**GitHub:** [@tgorzelniowski](https://github.com/tgorzelniowski)
**Mail** : gorzelniowski59@gmail.com

---

## ⚖️ Licencja i prawa autorskie

© 2026 Tomasz Gorzelniowski. **Wszelkie prawa zastrzeżone.**

Kod źródłowy jest udostępniony wyłącznie w celach poglądowych (portfolio). Kopiowanie, modyfikowanie, uruchamianie we własnych projektach oraz dystrybucja kodu lub jego fragmentów bez pisemnej zgody autora jest zabronione.
