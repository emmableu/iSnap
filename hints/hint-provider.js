require('hint-display');

function HintProvider(url, displays, reloadCode) {
    this.init(url, displays, reloadCode);
}

HintProvider.prototype.init = function(url, displays, reloadCode) {
    this.url = url;
    this.lastHints = [];
    this.requestNumber = 0;
    this.reloadCode = reloadCode;
    // For viewing purposes, this overrides server requests with static hints
    this.forcedHints;

    if (!displays) displays = [];
    if (displays instanceof HintDisplay) displays = [displays];
    this.displays = displays;

    var myself = this;

    if (reloadCode) {
        window.onunload = function() {
            myself.saveCode();
        };

        myself.loadCode();
    }

    if (displays.length == 0) return;

    displays.forEach(function(display) {
        display.enabled = true;
        display.showing = false;
    });

    // First check the parameters for a hints parameter
    var params = getSearchParameters();
    if ('hints' in params) {
        // If it's present, use that value, rather than assignment config values
        if (params['hints'] !== 'true') return;
        this.alwaysHint = true;
    }

    Trace.onCodeChanged = function(code) {
        myself.code = code;
        myself.getHintsFromServer();
    };

    Assignment.onChanged(function(assignment) {
        myself.loadAssignment();
    });

    extendObject(window, 'onWorldLoaded', function(base) {
        base.call(this);
        myself.displays.forEach(function(display) {
            display.initDisplay();
        });
        myself.loadAssignment();
    });
};

HintProvider.prototype.isActive = function() {
    var assignment = Assignment.get();
    return assignment && (this.alwaysHint || assignment.hints);
};

HintProvider.prototype.showDisplays = function() {
    this.displays.forEach(function(display) {
        if (!display.showing) {
            display.show();
            display.showing = true;
        }
    });
    this.getHintsFromServer();
};

HintProvider.prototype.hideDisplays = function() {
    this.displays.forEach(function(display) {
        if (display.showing) {
            display.clear();
            display.hide();
            display.showing = false;
        }
    });
};

HintProvider.prototype.loadAssignment = function() {
    if (this.isActive()) {
        this.showDisplays();
    } else {
        this.hideDisplays();
    }
};

HintProvider.prototype.clearDisplays = function() {
    this.displays.forEach(function(display) {
        display.clear();
    });
};

HintProvider.prototype.getHintsFromServer = function() {
    if (!this.code || !this.isActive()) return;
    this.clearDisplays();

    if (!this.displays.some(function(display) {
        return display.enabled;
    })) return;

    if (this.forcedHints) {
        this.processHints(this.forcedHints);
        return;
    }

    var myself = this;

    if (this.lastXHR) {
        // cancel the last hit request's callbacks
        this.lastXHR.onload = null;
        this.lastXHR.onerror = null;
    }

    var hintTypes = this.displays.map(function(display) {
        return display.getHintType();
    }).join(';');

    var xhr = createCORSRequest('POST',
        this.url + '?assignmentID=' + Assignment.getID() +
        '&hintTypes=' + encodeURIComponent(hintTypes));
    if (!xhr) {
        myself.showError('CORS not supported on this browser.');
        return;
    }
    this.lastXHR = xhr;

    // Response handlers.
    var requestNumber = ++this.requestNumber;
    xhr.onload = function() {
        myself.processHintRequest(xhr.responseText, requestNumber);
    };

    xhr.onerror = function(e) {
        myself.showError('Error contacting hint server!');
    };

    xhr.send(this.code);
};

HintProvider.prototype.showError = function(message) {
    Trace.logErrorMessage(message);
    this.displays.forEach(function(display) {
        if (display.enabled) {
            display.showError(message);
        }
    });
};

HintProvider.prototype.processHintRequest = function(json, requestNumber) {
    // If a more recent request has been fired, wait on that one
    // This is below the log statement because if we have pending code
    // changes to log, they'll flush and call a new request, and this one
    // should then be ignored.
    if (this.requestNumber != requestNumber) return;

    var hints;
    try {
        hints = JSON.parse(json);
    } catch (e) {
        this.showError('Bad JSON: ' + json);
        return;
    }

    this.processHints(hints);
};

HintProvider.prototype.processHints = function(hints) {
    Trace.log('HintProvider.processHints', hints);

    var nErrors = 0, nHints = 0;
    for (var i = 0; i < hints.length; i++) {
        var hint = hints[i];
        if (hint.error) {
            Trace.logError(hint);
            nErrors++;
            continue;
        }
        this.displays.forEach(function(display) {
            if (display.enabled) {
                try {
                    display.showHint(hint);
                    nHints++;
                } catch (e2) {
                    Trace.logError(e2);
                    nErrors++;
                }
            }
        });
    }
    this.displays.forEach(function(display) {
        if (display.enabled) {
            try {
                display.finishedHints();
            } catch (e2) {
                Trace.logError(e2);
            }
        }
    });
    this.lastHints = hints;

    if (nErrors > 0 && nHints == 0) {
        // It's possible to have some hints and errors, but if we only have
        // errors, we need to have the hint providers show an error message
        this.showError('Error generating hints!');
    }
};

HintProvider.prototype.saveCode = function() {
    if (typeof(Storage) !== 'undefined' && localStorage && this.code) {
        localStorage.setItem('lastCode-' + Assignment.getID(), this.code);
    }
};

HintProvider.prototype.loadCode = function() {
    if (typeof(Storage) !== 'undefined' && localStorage) {
        var code = localStorage.getItem('lastCode-' + Assignment.getID());
        if (code) {
            if (window.ide) {
                window.ide.droppedText(code);
            } else {
                var myself = this;
                setTimeout(function() {
                    myself.loadCode();
                }, 100);
            }
        }
    }
};

HintProvider.prototype.setDisplayEnabled = function(displayType, enabled) {
    var refresh = false;
    this.displays.forEach(function(display) {
        if (display instanceof displayType) {
            refresh = refresh || (enabled && !display.enabled);
            display.enabled = enabled;
            if (!enabled) {
                display.clear();
            }
        }
    });
    if (refresh) this.getHintsFromServer();
};