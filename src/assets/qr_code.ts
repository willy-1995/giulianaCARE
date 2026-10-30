import * as QRCode from 'qrcode';
import * as fs from 'fs';

async function generateFlyerQRCode(websiteUrl: string, outputPath: string) {
  try {
    // Generiert den QR-Code als SVG-String
    // Wir nutzen ein hohes "margin" für sauberen Weißraum drumherum
    const svgString = await QRCode.toString(websiteUrl, {
      type: 'svg',
      width: 1000, // Hohe Auflösung als Basis
      margin: 2,
      color: {
        dark: '#000000',  // Klassisches Schwarz für besten Kontrast beim Druck
        light: '#ffffff'  // Weißer Hintergrund
      }
    });

    // Speichert das Ganze als .svg Datei, die du für den Flyer nutzen kannst
    fs.writeFileSync(outputPath, svgString);
    console.log(`QR-Code erfolgreich gespeichert unter: ${outputPath}`);
  } catch (error) {
    console.error('Fehler beim Erstellen des QR-Codes:', error);
  }
}

// Beispiel-Aufruf: Ersetze die URL mit deiner Website
generateFlyerQRCode('https://www.giuliana-care.de', './flyer-qr-code.svg');