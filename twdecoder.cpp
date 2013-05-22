#include <iostream>
#include <fstream>
#include <string>
#include <ctime>

/**
  * TW Decoder
  *
  * URL-dekodiert DieStämme-Weltdaten. (Relevant für Spieler- und Dorfdaten.)
  *
  * Anwendung: twdecoder <Quelldatei> <Zieldatei>
  * Beispiel:  twdecoder tribe.txt tribe.decoded.txt
  *
  * @author: Robert Nitsch <dev@robertnitsch.de>
  */
using namespace std;

/**
  * Hilfsvariable für die Funktion urldecode
  */
string urldecode_result = "";

/**
 * Hilfsfunktion für die Funktion urldecode:
 * Wandelt eine einzige Hex-Zahl in eine Dezimalzahl um. Die Hex-Zahl wird in Form von 2 Chars angegeben.
 *
 * Beispiel:
 *   Die Hexzahl AC würde folgendermaßen umgewandelt werden:
 *     >>> hex2int('A', 'C')
 *     172
 */
inline int hex2int(const char& char1, const char& char2) {
    int result = 0;

    if(char2 <= 57) {
        result += (int)char2 - 48;
    }
    else {
        result += (int)char2 - 55;
    }

    if(char1 <= 57) {
        result += ((int)char1 - 48) * 16;
    }
    else {
        result += ((int)char1 - 55) * 16;
    }

    return result;
}

/**
   * Erhält einen String und URL-decoded ihn.
   *
   * Das heißt:
   *   => %XX wird umgewandelt zu dem Zeichen mit dem ASCII-Code XX, sofern XX eine Hexadezimalzahl darstellt.
   *   => +       wird umgewandelt in jeweils ein Leerzeichen.
   *
   * Wichtig:
   *   Es wird vorausgesetzt, dass es sich bei dem gegebenen String wirklich um einen urlenkodierten String handelt.
   *   urldecode führt keine eigenen Checks durch!
   *
   * Beispiel:
   *   >>> urldecode("Dieser+String+ist+urldecoded.")
   *   "Dieser String ist urldecoded."
*/
inline string& urldecode(const string& str)
{
    int max = str.length();
    urldecode_result = "";

    for(int i=0; i < max; ++i) {
        switch(str[i]) {
            // Die Spalten-Trennungs-Kommas werden umgewandelt zu Tabs um in Dorfnamen auch Kommas zu ermöglichen.
            // Andernfalls gibt es einen Konflikt zwischen den originalen und den url-dekodierten Kommas.
            case ',':
                // Tabulator daraus machen
                urldecode_result += "\t";
                break;

            // Handelt es sich um ein + Zeichen?
            case '+':
                // Dann zu einem Leerzeichen umwandeln!
                urldecode_result += " ";
                break;

            // Handelt es sich um ein % Zeichen?
            case '%':
                // Das Zeichen dekodieren
                urldecode_result += (char) hex2int(str[i+1], str[i+2]);
                // Gleich 3 Zeichen weiter springen, statt nur 1 Zeichen (also +2).
                i += 2;
                break;

            // Es handelt sich nicht um ein spezielles Zeichen...
            default:
                // Das Zeichen einfach übernehmen
                urldecode_result += str[i];
        }
    }

    return urldecode_result;
}

int main(int argc, char* argv[])
{
    // Argumente überprüfen
    if(argc != 3) {
        cerr << "   Usage:    twdecoder <source file> <destination file>" << endl;
        cerr << "   Example:  twdecoder tribe.txt tribe.decoded.txt" << endl << endl;
        cerr << "twdecoder   -   Copyright: Robert Nitsch, 2008   -   Contact: dev@robertnitsch.de" << endl;
        return 1;
    }

    // Startzeit speichern
    clock_t clock_start = clock();

    // Filestreams erzeugen
    ifstream source(argv[1]);
    ofstream destination(argv[2]);
    source.sync_with_stdio(false);
    destination.sync_with_stdio(false);

    // Filestreams überprüfen
    if(source.fail()) {
        cerr << "Couldn't open source file." << endl;
        return 1;
    }
    if(destination.fail()) {
        cerr << "Couldn't open destination file." << endl;
        return 1;
    }

    // UTF-8 BOM-Sequenz schreiben
    destination << (char)0xEF << (char)0xBB << (char)0xBF;

    // Zeile für Zeile urldecoden
    string s = "";
    s.reserve(100);
    urldecode_result.reserve(100);
    while(getline(source, s)) {
        destination << urldecode(s) << "\n";
    }

    // Filestreams schließen
    source.close();
    destination.close();

    // Endzeit speichern
    clock_t clock_end = clock();

    cout << "Sucessfully decoded " << argv[1] << " to " << argv[2] << " in " << (clock_end-clock_start)/(float)CLOCKS_PER_SEC << "s" << endl;
    cout.flush();
    return 0;
}
