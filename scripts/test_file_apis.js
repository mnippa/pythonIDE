/**
 * Integration Test für File Management APIs
 * Kann in Browser-Console ausgeführt werden: 
 * 1. File Manager API einladen
 * 2. Script ausführen
 */

class FileManagerTest {
    constructor() {
        this.fm = new FileManager('/api');
        this.results = [];
        this.testsFailed = 0;
        this.testsPassed = 0;
    }

    log(message, type = 'info') {
        const timestamp = new Date().toLocaleTimeString();
        console.log(`[${timestamp}] ${message}`);
        this.results.push({ timestamp, message, type });
    }

    async assert(condition, message) {
        if (condition) {
            this.log(`✓ ${message}`, 'pass');
            this.testsPassed++;
        } else {
            this.log(`✗ ${message}`, 'fail');
            this.testsFailed++;
        }
    }

    async run() {
        console.clear();
        this.log('='.repeat(50), 'header');
        this.log('File Manager API Integration Test', 'header');
        this.log('='.repeat(50), 'header');

        // Get first project for testing
        const projects = await this.getProjects();
        if (projects.length === 0) {
            this.log('✗ Keine Projekte gefunden. Bitte zuerst Projekte erstellen.', 'error');
            return this.printSummary();
        }

        const projectId = projects[0].id;
        this.fm.setProject(projectId);
        this.log(`\nVerwendetes Projekt: ${projects[0].name} (ID: ${projectId})`);

        this.log('\n--- Test 1: Ordner erstellen ---');
        await this.testCreateFolder();

        this.log('\n--- Test 2: Ordner auflisten ---');
        await this.testListFolders();

        this.log('\n--- Test 3: Dateien erstellen ---');
        await this.testCreateFiles();

        this.log('\n--- Test 4: Dateien auflisten ---');
        await this.testListFiles();

        this.log('\n--- Test 5: Datei lesen ---');
        await this.testReadFile();

        this.log('\n--- Test 6: Datei aktualisieren ---');
        await this.testUpdateFile();

        this.log('\n--- Test 7: Fehlerbehandlung ---');
        await this.testErrors();

        this.printSummary();
    }

    async getProjects() {
        try {
            const response = await fetch('/api/projects/list.php', {
                credentials: 'include'
            });
            const data = await response.json();
            return data.projects || [];
        } catch (error) {
            this.log(`Fehler beim Laden von Projekten: ${error.message}`, 'error');
            return [];
        }
    }

    async testCreateFolder() {
        try {
            const result = await this.fm.createFolder('TestOrdner', null, 'Testbeschreibung');
            await this.assert(result.ok === true, 'Ordner erstellt');
            await this.assert(result.folder?.id > 0, 'Ordner-ID vorhanden');
            await this.assert(result.folder?.name === 'TestOrdner', 'Ordnername korrekt');
            
            // Speichere Ordner-ID für weitere Tests
            this.testFolderId = result.folder?.id;
            
            return result.folder?.id;
        } catch (error) {
            this.log(`Fehler: ${error.message}`, 'error');
            this.testsFailed++;
        }
    }

    async testListFolders() {
        try {
            const result = await this.fm.listFolders();
            await this.assert(result.ok === true, 'Ordner aufgelistet');
            await this.assert(Array.isArray(result.folders), 'Ordner-Array vorhanden');
            await this.assert(
                result.folders.some(f => f.id === this.testFolderId),
                'Testordner in Liste enthalten'
            );
        } catch (error) {
            this.log(`Fehler: ${error.message}`, 'error');
            this.testsFailed++;
        }
    }

    async testCreateFiles() {
        try {
            // Python-Datei
            const pyResult = await this.fm.createFile(
                'test.py',
                'python',
                "def hello():\n    print('Hello World')",
                this.testFolderId
            );
            await this.assert(pyResult.ok === true, 'Python-Datei erstellt');
            await this.assert(pyResult.file?.file_type === 'python', 'Dateityp: python');
            this.testPyFileId = pyResult.file?.id;

            // JSON-Datei
            const jsonResult = await this.fm.createFile(
                'config.json',
                'json',
                JSON.stringify({ version: '1.0', test: true }, null, 2),
                this.testFolderId
            );
            await this.assert(jsonResult.ok === true, 'JSON-Datei erstellt');
            await this.assert(jsonResult.file?.file_type === 'json', 'Dateityp: json');
            this.testJsonFileId = jsonResult.file?.id;

            // Text-Datei
            const txtResult = await this.fm.createFile(
                'readme.txt',
                'text',
                'Dies ist eine Test-Datei',
                this.testFolderId
            );
            await this.assert(txtResult.ok === true, 'Text-Datei erstellt');
            await this.assert(txtResult.file?.file_type === 'text', 'Dateityp: text');
            this.testTxtFileId = txtResult.file?.id;

        } catch (error) {
            this.log(`Fehler: ${error.message}`, 'error');
            this.testsFailed++;
        }
    }

    async testListFiles() {
        try {
            const result = await this.fm.listFiles(this.testFolderId);
            await this.assert(result.ok === true, 'Dateien aufgelistet');
            await this.assert(result.files?.length >= 3, 'Mindestens 3 Dateien');
            await this.assert(
                result.files.some(f => f.id === this.testPyFileId),
                'Python-Datei in Liste'
            );
        } catch (error) {
            this.log(`Fehler: ${error.message}`, 'error');
            this.testsFailed++;
        }
    }

    async testReadFile() {
        try {
            const result = await this.fm.readFile(this.testPyFileId);
            await this.assert(result.ok === true, 'Datei gelesen');
            await this.assert(result.file?.content?.includes('hello'), 'Dateiinhalt korrekt');
            await this.assert(result.file?.name === 'test.py', 'Dateiname korrekt');
        } catch (error) {
            this.log(`Fehler: ${error.message}`, 'error');
            this.testsFailed++;
        }
    }

    async testUpdateFile() {
        try {
            const newContent = "def goodbye():\n    print('Goodbye World')";
            const result = await this.fm.updateFile(this.testPyFileId, newContent);
            await this.assert(result.ok === true, 'Datei aktualisiert');

            // Überprüfe, ob Update funktioniert hat
            const readResult = await this.fm.readFile(this.testPyFileId);
            await this.assert(
                readResult.file?.content?.includes('goodbye'),
                'Neuer Inhalt gespeichert'
            );
        } catch (error) {
            this.log(`Fehler: ${error.message}`, 'error');
            this.testsFailed++;
        }
    }

    async testErrors() {
        try {
            // Test: Ungültige Ordner-ID
            try {
                await this.fm.setFolder(null);
                const result = await this.fm.createFile('test.py', 'python', 'test', 999999);
                await this.assert(
                    result.ok === false,
                    'Fehler bei ungültiger Ordner-ID erkannt'
                );
            } catch (error) {
                await this.assert(
                    error.message.includes('404'),
                    'Fehlercode 404 bei ungültiger Ordner-ID'
                );
            }

            // Test: Doppelter Dateiname
            try {
                const r1 = await this.fm.createFile('test.py', 'python', 'test', this.testFolderId);
                // Zweiter Versuch sollte fehlschlagen mit 409 Conflict
                const r2 = await this.fm.createFile('test.py', 'python', 'test', this.testFolderId);
                await this.assert(r2.ok === false, 'Fehler bei doppeltem Dateinamen');
            } catch (error) {
                await this.assert(
                    error.message.includes('409'),
                    'Fehlercode 409 bei doppeltem Dateinamen'
                );
            }

        } catch (error) {
            this.log(`Fehler: ${error.message}`, 'error');
        }
    }

    printSummary() {
        this.log('\n' + '='.repeat(50), 'header');
        this.log(`Tests bestanden: ${this.testsPassed}`, 'pass');
        this.log(`Tests fehlgeschlagen: ${this.testsFailed}`, this.testsFailed > 0 ? 'fail' : 'pass');
        this.log(`Gesamt: ${this.testsPassed + this.testsFailed}`, 'header');
        this.log('='.repeat(50), 'header');

        // Export results as JSON
        console.log('\nTest Results JSON:');
        console.log(JSON.stringify(this.results, null, 2));
    }
}

// Führe Tests aus
const tester = new FileManagerTest();
tester.run().catch(error => console.error('Test-Fehler:', error));
