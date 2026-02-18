/**
 * Test Mode Manager
 * Handles all sessionStorage operations for admin test assignments
 * Completely isolated from normal user_tasks database storage
 */

const TestMode = {
    
    // Key für sessionStorage
    STORAGE_KEY_PREFIX: 'test_',
    
    /**
     * Initialize test mode for an assignment
     * @param {number} assignmentId 
     * @returns {object} Initial test state
     */
    initTestMode(assignmentId) {
        const testState = {
            assignmentId: assignmentId,
            tasks: {},
            startTime: new Date().toISOString(),
            isTestMode: true
        };
        
        sessionStorage.setItem(
            this.STORAGE_KEY_PREFIX + 'state_' + assignmentId,
            JSON.stringify(testState)
        );
        
        // Mark as test mode globally
        sessionStorage.setItem(this.STORAGE_KEY_PREFIX + 'mode_active', 'true');
        
        return testState;
    },
    
    /**
     * Check if test mode is active
     * @returns {boolean}
     */
    isTestMode() {
        return sessionStorage.getItem(this.STORAGE_KEY_PREFIX + 'mode_active') === 'true';
    },
    
    /**
     * Get current assignment ID in test mode
     * @returns {number|null}
     */
    getCurrentAssignmentId() {
        const keys = Object.keys(sessionStorage);
        for (let key of keys) {
            if (key.startsWith(this.STORAGE_KEY_PREFIX + 'state_')) {
                const state = JSON.parse(sessionStorage.getItem(key));
                return state.assignmentId;
            }
        }
        return null;
    },
    
    /**
     * Get full test state for assignment
     * @param {number} [assignmentId] - If not provided, use current
     * @returns {object}
     */
    getTestState(assignmentId) {
        if (!assignmentId) {
            assignmentId = this.getCurrentAssignmentId();
        }
        if (!assignmentId) return null;
        
        const state = sessionStorage.getItem(this.STORAGE_KEY_PREFIX + 'state_' + assignmentId);
        return state ? JSON.parse(state) : null;
    },
    
    /**
     * Update test state
     * @param {object} updates 
     * @param {number} [assignmentId]
     */
    setTestState(updates, assignmentId) {
        if (!assignmentId) {
            assignmentId = this.getCurrentAssignmentId();
        }
        if (!assignmentId) return;
        
        const state = this.getTestState(assignmentId) || { assignmentId, tasks: {} };
        Object.assign(state, updates);
        
        sessionStorage.setItem(
            this.STORAGE_KEY_PREFIX + 'state_' + assignmentId,
            JSON.stringify(state)
        );
    },
    
    /**
     * Get task state (attempts, iterations, status, etc)
     * @param {number} taskId 
     * @param {number} [assignmentId]
     * @returns {object}
     */
    getTaskState(taskId, assignmentId) {
        if (!assignmentId) {
            assignmentId = this.getCurrentAssignmentId();
        }
        
        const state = this.getTestState(assignmentId);
        if (!state || !state.tasks) return null;
        
        return state.tasks[taskId] || null;
    },
    
    /**
     * Set/update task state
     * @param {number} taskId 
     * @param {object} taskState - { status, attempts, current_iteration, variable_values, etc }
     * @param {number} [assignmentId]
     */
    setTaskState(taskId, taskState, assignmentId) {
        if (!assignmentId) {
            assignmentId = this.getCurrentAssignmentId();
        }
        if (!assignmentId) return;
        
        const state = this.getTestState(assignmentId);
        if (!state) return;
        
        if (!state.tasks) state.tasks = {};
        state.tasks[taskId] = Object.assign(state.tasks[taskId] || {}, taskState);
        
        sessionStorage.setItem(
            this.STORAGE_KEY_PREFIX + 'state_' + assignmentId,
            JSON.stringify(state)
        );
    },
    
    /**
     * Get all task states
     * @param {number} [assignmentId]
     * @returns {object}
     */
    getAllTaskStates(assignmentId) {
        if (!assignmentId) {
            assignmentId = this.getCurrentAssignmentId();
        }
        
        const state = this.getTestState(assignmentId);
        return state ? state.tasks : {};
    },
    
    /**
     * Initialize a task for testing (first time)
     * @param {number} taskId 
     * @param {number} maxIterations
     * @param {number} [assignmentId]
     */
    initializeTask(taskId, maxIterations, assignmentId) {
        const taskState = {
            taskId: taskId,
            status: 'unbearbeitet',
            attempts: 0,
            current_iteration: 1,
            max_iterations: maxIterations,
            variable_values: null,
            created_at: new Date().toISOString(),
            submissions: []  // Track each submission for debugging
        };
        
        this.setTaskState(taskId, taskState, assignmentId);
        return taskState;
    },
    
    /**
     * Record a submission attempt (for debugging/review)
     * @param {number} taskId 
     * @param {object} submitData - What was submitted
     * @param {object} response - What API returned
     * @param {number} [assignmentId]
     */
    recordSubmission(taskId, submitData, response, assignmentId) {
        if (!assignmentId) {
            assignmentId = this.getCurrentAssignmentId();
        }
        
        const taskState = this.getTaskState(taskId, assignmentId);
        if (!taskState) return;
        
        if (!taskState.submissions) taskState.submissions = [];
        
        taskState.submissions.push({
            timestamp: new Date().toISOString(),
            submitted: submitData,
            response: response
        });
        
        // Keep only last 10 submissions per task
        if (taskState.submissions.length > 10) {
            taskState.submissions.shift();
        }
        
        this.setTaskState(taskId, taskState, assignmentId);
    },
    
    /**
     * Clear test mode completely
     * Called when test window closes
     * @param {number} [assignmentId]
     */
    resetTestMode(assignmentId) {
        if (!assignmentId) {
            assignmentId = this.getCurrentAssignmentId();
        }
        
        if (assignmentId) {
            sessionStorage.removeItem(this.STORAGE_KEY_PREFIX + 'state_' + assignmentId);
        }
        
        // If no more test sessions, disable test mode globally
        const keys = Object.keys(sessionStorage);
        const hasTestStates = keys.some(k => k.startsWith(this.STORAGE_KEY_PREFIX + 'state_'));
        
        if (!hasTestStates) {
            sessionStorage.removeItem(this.STORAGE_KEY_PREFIX + 'mode_active');
        }
    },
    
    /**
     * Export test session as JSON (for debugging)
     * @param {number} [assignmentId]
     * @returns {string} JSON export
     */
    exportSession(assignmentId) {
        if (!assignmentId) {
            assignmentId = this.getCurrentAssignmentId();
        }
        
        const state = this.getTestState(assignmentId);
        return state ? JSON.stringify(state, null, 2) : null;
    },
    
    /**
     * Import test session from JSON (for debugging)
     * @param {string} jsonData 
     * @param {number} [assignmentId]
     */
    importSession(jsonData, assignmentId) {
        try {
            const state = JSON.parse(jsonData);
            if (!assignmentId) {
                assignmentId = state.assignmentId;
            }
            
            sessionStorage.setItem(
                this.STORAGE_KEY_PREFIX + 'state_' + assignmentId,
                JSON.stringify(state)
            );
            sessionStorage.setItem(this.STORAGE_KEY_PREFIX + 'mode_active', 'true');
            
            return true;
        } catch (e) {
            console.error('Failed to import session:', e);
            return false;
        }
    }
};

// Make globally available
window.TestMode = TestMode;

// Export for use in tests
if (typeof module !== 'undefined' && module.exports) {
    module.exports = TestMode;
}
