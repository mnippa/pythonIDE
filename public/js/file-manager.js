/**
 * File Management API Client Library
 * Frontend-Integration für Ordner- und Dateiverwaltung
 */

class FileManager {
    constructor(baseUrl = '/api') {
        this.baseUrl = baseUrl;
        this.currentProject = null;
        this.currentFolder = null;
    }

    /**
     * Setze das aktuelle Projekt
     */
    setProject(projectId) {
        this.currentProject = projectId;
        return this;
    }

    /**
     * Setze den aktuellen Ordner
     */
    setFolder(folderId) {
        this.currentFolder = folderId;
        return this;
    }

    /**
     * Fehlerbehandlung
     */
    async handleResponse(response) {
        const data = await response.json();
        
        if (!response.ok) {
            const error = data.error || 'Unknown error';
            throw new Error(`${response.status}: ${error}`);
        }
        
        return data;
    }

    // ============================================
    // FOLDER OPERATIONS
    // ============================================

    /**
     * Erstelle einen neuen Ordner
     * @param {string} name - Ordnername
     * @param {number} parentFolderId - Parent folder ID (optional)
     * @param {string} description - Beschreibung (optional)
     */
    async createFolder(name, parentFolderId = null, description = '') {
        if (!this.currentProject) throw new Error('Project not set');

        const response = await fetch(
            `${this.baseUrl}/projects/folders.php?action=create&project_id=${this.currentProject}`,
            {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'include',
                body: JSON.stringify({
                    name,
                    parent_folder_id: parentFolderId,
                    description
                })
            }
        );

        return this.handleResponse(response);
    }

    /**
     * Liste Ordner auf
     * @param {number} parentFolderId - Parent folder ID (optional, null = root)
     */
    async listFolders(parentFolderId = null) {
        if (!this.currentProject) throw new Error('Project not set');

        let url = `${this.baseUrl}/projects/folders.php?action=list&project_id=${this.currentProject}`;
        if (parentFolderId !== null) {
            url += `&parent_id=${parentFolderId}`;
        }

        const response = await fetch(url, {
            method: 'GET',
            credentials: 'include'
        });

        return this.handleResponse(response);
    }

    /**
     * Benenne Ordner um
     * @param {number} folderId - Ordner-ID
     * @param {string} newName - Neuer Name
     */
    async renameFolder(folderId, newName) {
        if (!this.currentProject) throw new Error('Project not set');

        const response = await fetch(
            `${this.baseUrl}/projects/folders.php?action=rename&project_id=${this.currentProject}`,
            {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'include',
                body: JSON.stringify({
                    folder_id: folderId,
                    name: newName
                })
            }
        );

        return this.handleResponse(response);
    }

    /**
     * Lösche Ordner
     * @param {number} folderId - Ordner-ID
     */
    async deleteFolder(folderId) {
        if (!this.currentProject) throw new Error('Project not set');

        const response = await fetch(
            `${this.baseUrl}/projects/folders.php?action=delete&project_id=${this.currentProject}`,
            {
                method: 'DELETE',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'include',
                body: JSON.stringify({ folder_id: folderId })
            }
        );

        return this.handleResponse(response);
    }

    // ============================================
    // FILE OPERATIONS
    // ============================================

    /**
     * Erstelle/uploade eine Datei
     * @param {string} name - Dateiname
     * @param {string} fileType - Dateityp (python, json, image, text, other)
     * @param {string|Blob} content - Dateiinhalt oder Datei-Blob
     * @param {number} folderId - Ziel-Ordner (optional)
     */
    async createFile(name, fileType, content, folderId = null) {
        if (!this.currentProject) throw new Error('Project not set');

        const formData = new FormData();
        formData.append('folder_id', folderId || this.currentFolder);
        formData.append('name', name);
        formData.append('file_type', fileType);

        if (content instanceof Blob) {
            formData.append('file', content, name);
        } else {
            formData.append('content', content);
        }

        const response = await fetch(
            `${this.baseUrl}/projects/files.php?action=create&project_id=${this.currentProject}`,
            {
                method: 'POST',
                credentials: 'include',
                body: formData
            }
        );

        return this.handleResponse(response);
    }

    /**
     * Liste Dateien auf
     * @param {number} folderId - Ordner-ID (optional, null = root)
     */
    async listFiles(folderId = null) {
        if (!this.currentProject) throw new Error('Project not set');

        let url = `${this.baseUrl}/projects/files.php?action=list&project_id=${this.currentProject}`;
        if (folderId !== null) {
            url += `&folder_id=${folderId}`;
        }

        const response = await fetch(url, {
            method: 'GET',
            credentials: 'include'
        });

        return this.handleResponse(response);
    }

    /**
     * Lese Dateiinhalt
     * @param {number} fileId - Datei-ID
     */
    async readFile(fileId) {
        if (!this.currentProject) throw new Error('Project not set');

        const response = await fetch(
            `${this.baseUrl}/projects/files.php?action=read&project_id=${this.currentProject}&file_id=${fileId}`,
            {
                method: 'GET',
                credentials: 'include'
            }
        );

        return this.handleResponse(response);
    }

    /**
     * Aktualisiere Dateiinhalt
     * @param {number} fileId - Datei-ID
     * @param {string} content - Neuer Inhalt
     */
    async updateFile(fileId, content) {
        if (!this.currentProject) throw new Error('Project not set');

        const response = await fetch(
            `${this.baseUrl}/projects/files.php?action=update&project_id=${this.currentProject}`,
            {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'include',
                body: JSON.stringify({
                    file_id: fileId,
                    content
                })
            }
        );

        return this.handleResponse(response);
    }

    /**
     * Lösche Datei
     * @param {number} fileId - Datei-ID
     */
    async deleteFile(fileId) {
        if (!this.currentProject) throw new Error('Project not set');

        const response = await fetch(
            `${this.baseUrl}/projects/files.php?action=delete&project_id=${this.currentProject}`,
            {
                method: 'DELETE',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'include',
                body: JSON.stringify({ file_id: fileId })
            }
        );

        return this.handleResponse(response);
    }

    // ============================================
    // ASSIGNMENT FILE OPERATIONS (Read-Only)
    // ============================================

    /**
     * Liste Assignment-Dateien auf (Nur-Lesen)
     * @param {number} assignmentId - Assignment-ID
     * @param {number} taskId - Task-ID (optional)
     */
    async listAssignmentFiles(assignmentId, taskId = null) {
        let url = `${this.baseUrl}/assignments/files.php?action=list&assignment_id=${assignmentId}`;
        if (taskId !== null) {
            url += `&task_id=${taskId}`;
        }

        const response = await fetch(url, {
            method: 'GET',
            credentials: 'include'
        });

        return this.handleResponse(response);
    }

    /**
     * Lese Assignment-Datei (Nur-Lesen)
     * @param {number} assignmentId - Assignment-ID
     * @param {number} fileId - Datei-ID
     */
    async readAssignmentFile(assignmentId, fileId) {
        const response = await fetch(
            `${this.baseUrl}/assignments/files.php?action=read&assignment_id=${assignmentId}&file_id=${fileId}`,
            {
                method: 'GET',
                credentials: 'include'
            }
        );

        return this.handleResponse(response);
    }

    // ============================================
    // BULK OPERATIONS
    // ============================================

    /**
     * Lade mehrere Dateien von einem Ordner
     */
    async loadFolderStructure(folderId = null) {
        const folders = await this.listFolders(folderId);
        const files = await this.listFiles(folderId);

        return {
            folders: folders.folders || [],
            files: files.files || []
        };
    }

    /**
     * Erstelle Verzeichnisstruktur rekursiv
     */
    async createFromTemplate(structure, parentId = null) {
        const results = {
            folders: [],
            files: []
        };

        // Erstelle Ordner
        if (structure.folders) {
            for (const folder of structure.folders) {
                const result = await this.createFolder(folder.name, parentId, folder.description);
                results.folders.push(result.folder);

                // Rekursiv Unterordner erstellen
                if (folder.subFolders) {
                    const subResults = await this.createFromTemplate(
                        { folders: folder.subFolders },
                        result.folder.id
                    );
                    results.folders.push(...subResults.folders);
                }
            }
        }

        // Erstelle Dateien
        if (structure.files && parentId) {
            for (const file of structure.files) {
                const result = await this.createFile(
                    file.name,
                    file.type,
                    file.content,
                    parentId
                );
                results.files.push(result.file);
            }
        }

        return results;
    }

    /**
     * Lade Datei herunter (Create Blob URL)
     */
    async downloadFile(fileId) {
        const data = await this.readFile(fileId);
        const file = data.file;

        let blobData = file.content;
        if (file.is_base64) {
            blobData = atob(file.content);
        }

        const blob = new Blob([blobData], { type: file.mime_type });
        return {
            blob,
            url: URL.createObjectURL(blob),
            filename: file.name
        };
    }
}

// Exportiere für Module
if (typeof module !== 'undefined' && module.exports) {
    module.exports = FileManager;
}
