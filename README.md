# 🎥 VideoPreview für REDAXO

> 👋 Dieses AddOn wurde eingestellt. Die Effekte wurden in das ffmpeg-AddOn übernommen


Dieses AddOn erweitert den REDAXO Media Manager um zwei neue Effekte zur automatischen Generierung von Vorschauen aus Videos. Die Effekte eignen sich ideal für Medienpools, Galerien oder überall dort, wo ein schneller Einblick in Videoinhalte benötigt wird.

## 🎯 Features

- **MP4-Vorschauen**: Kurze, stumme Videoausschnitte
- **WebP-Vorschauen**: Animierte Bilder mit kleiner Dateigröße
- **Intelligente Positionierung**: Auswahl zwischen Start, Mitte oder Ende des Videos
- **Optimiert für Text**: Spezielle Filter für bessere Lesbarkeit von Texten im Video
- **Flexible Konfiguration**: Von minimaler bis maximaler Kompression
- **Mehrsprachig**: Deutsch und Englisch integriert

## 🔧 Systemvoraussetzungen

- REDAXO 5.17 oder höher
- PHP 8.1 oder höher
- FFmpeg auf dem Server
- Ausreichend Arbeitsspeicher für Videoverarbeitung

## 📦 Installation

1. Im REDAXO-Backend zum Installer navigieren
2. "VideoPreview" suchen
3. Installieren und aktivieren
4. FFmpeg-Verfügbarkeit wird automatisch geprüft

## 🎮 Verwendung

### Im Media Manager

1. Einen neuen Media Manager Typ erstellen oder bestehenden bearbeiten
2. Einen der neuen Effekte hinzufügen:
   - "Video Vorschau (MP4, ohne Ton)"
   - "Video Vorschau (WebP)"

### Konfigurationsoptionen

Beide Effekte bieten folgende Einstellungen:

- **Position im Video**
  - Anfang (nach 2 Sekunden)
  - Mitte des Videos
  - 10 Sekunden vor Ende
  
- **Ausgabebreite**
  - Standard: 400px
  - Empfehlung: 400-800px für optimale Textdarstellung

- **Kompressionsstufe**
  1. Minimal (große Datei, beste Qualität)
  2. Niedrig (bessere Qualität)
  3. Standard (ausgewogen)
  4. Hoch (kleine Datei)
  5. Maximal (kleinste Datei)

- **FPS (Bilder pro Sekunde)**
  - Standard: 12
  - Empfehlung: 12-15 für beste Textlesbarkeit
  - Maximum: 30

- **Snippet-Länge**
  - Standard: 2 Sekunden
  - Maximum: 10 Sekunden

### Beispiel URL-Verwendung

```php
// MP4-Vorschau
echo rex_media_manager::getUrl('video_preview_mp4', 'mein_video.mp4');

// WebP-Vorschau
echo rex_media_manager::getUrl('video_preview_webp', 'mein_video.mp4');
```

## 🔍 Technische Details

### Unterstützte Videoformate
- MP4
- M4V
- AVI
- MOV
- WebM

### FFmpeg-Optimierungen

Der Code enthält spezielle FFmpeg-Filter für optimierte Videoqualität:

- **Textoptimierung**: Verbesserte Schärfe und Kontrast für bessere Lesbarkeit
- **Intelligentes Scaling**: Lanczos-Algorithmus mit akkurater Rundung
- **Adaptives Sharpening**: Unterschiedliche Unschärfemasken je nach Kompressionsstufe

### Speichernutzung

Die generierten Vorschauen werden temporär im Cache-Verzeichnis gespeichert und nach der Auslieferung automatisch gelöscht. Der Prozess ist memory-safe und für Produktivumgebungen optimiert.

## 💡 Empfehlungen

1. **Optimale Qualität für Text**
   - Niedrige Kompressionsstufe wählen
   - 12-15 FPS einstellen
   - Mindestbreite von 400px nutzen

2. **Minimale Dateigröße**
   - Höhere Kompressionsstufe verwenden
   - FPS auf 12 setzen
   - Kurze Snippet-Länge wählen

3. **Performance**
   - Vorschauen beim Upload generieren
   - URLs nach Möglichkeit cachen
   - WebP-Variante für schnelleres Laden nutzen

## 🐛 Problemlösung

### FFmpeg-Prüfung
Verfügbarkeit prüfen mit:
```bash
ffmpeg -version
```

### Leere Ausgabe
1. Videolänge prüfen
2. Video auf Beschädigung prüfen
3. Logs in `data/log/` analysieren

### Speicherprobleme
PHP-Memory-Limits anpassen:
```php
memory_limit = 256M
max_execution_time = 300
```

## 🤝 Support & Mitarbeit

- [GitHub](https://github.com/FriendsOfREDAXO/videopreview)
- Pull Requests sind willkommen

## 📄 Lizenz

MIT-Lizenz



## 👏 Credits
- FFmpeg für die Videoverarbeitung


**Lead**

[Thomas Skerbis](https://github.com/skerbis)

