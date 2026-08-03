/**
 * Extract last activity day from project.metadata.last_activity
 * @param {object} project
 * @returns
 */
function getLastActivity(project) {
    const lastDate = project.metadata ? project.metadata.last_activity : null;
    if (!lastDate) {
        return '0000-00-00';
    }
    return lastDate.split('T')[0];
}

/**
 * Render a bootstrap badge.
 *
 * @param {string} label
 * @param {string} variant bootstrap contextual color (ex : success, danger)
 * @returns
 */
function renderBadge(label, variant) {
    return `<span class="badge text-bg-${variant}">${label}</span>`;
}

/**
 * Render gitleaks report.
 *
 * @param {?object} gitleaks
 * @returns
 */
function renderGitleaks(gitleaks) {
    if (!gitleaks) {
        return renderBadge('NO-DATA', 'warning');
    }
    if (!gitleaks.success) {
        return renderBadge('FAILURE', 'danger');
    }
    const count = gitleaks.summary.count;
    return renderBadge(`SECRETS:&nbsp;${count}`, count > 0 ? 'danger' : 'success');
}

/**
 * Render trivy report.
 *
 * @param {?object} trivy
 * @returns
 */
function renderTrivy(trivy){
    if ( ! trivy ){
        return renderBadge('NO-DATA', 'warning');
    }

    if ( ! trivy.success ){
        return renderBadge('FAILURE', 'danger');
    }
    return `<div class="d-flex flex-column gap-1 align-items-start">`+['CRITICAL','HIGH'].map(severity => {
        const count = trivy.summary[severity];
        const failureBadge = severity === 'CRITICAL' ? 'danger' : 'warning';
        return renderBadge(`${severity}:&nbsp;${count}`, count > 0 ? failureBadge : 'success');
    }).join('')+`</div>`;
}

/**
 * Render a cell sorted on a numeric value instead of its HTML content.
 *
 * @param {object} data {display, order}
 * @param {string} type
 * @returns
 */
function renderOrdered(data, type) {
    return type === 'display' || type === 'filter' ? data.display : data.order;
}

/**
 * Load /api/projects to #projects tables.
 */
function loadProjects() {
    fetch('/api/projects').then(function (response) {
        if (response.status != 200) {
            throw new Error('fail to fetch repositories');
        }
        return response.json();
    }).then(function (projects) {
        let dataSet = projects.map(function (project) {
            const name = project.fullName;
            const sizeMo = (project.metadata.size / (1024 * 1024)).toFixed(1);
            const checks = project.checks;
            const detailsUrl = `/${project.id}`;
            const trivyOrder = checks.trivy && checks.trivy.summary ? checks.trivy.summary.CRITICAL * 1000000 + checks.trivy.summary.HIGH : -1;
            const gitleaksOrder = checks.gitleaks && checks.gitleaks.summary ? checks.gitleaks.summary.count : -1;
            const visibility = project.visibility ? project.visibility : 'unknown';
            return [
                `<a class="text-decoration-none fw-semibold" href="https://${name}" target="_blank" rel="noopener">${name}</a>`,
                project.archived ? renderBadge('YES', 'secondary') : renderBadge('NO', 'light'),
                renderBadge(visibility, visibility === 'public' ? 'success' : 'secondary'),
                renderBadge(checks.readme ? 'FOUND' : 'MISSING', checks.readme ? 'success' : 'danger'),
                renderBadge(checks.license ? checks.license : 'MISSING', checks.license ? 'success' : 'danger'),
                project.fetchedAt.split('T')[0],
                getLastActivity(project),
                sizeMo,
                { display: renderTrivy(checks.trivy), order: trivyOrder },
                { display: renderGitleaks(checks.gitleaks), order: gitleaksOrder },
                `<a class="btn btn-sm btn-outline-primary d-inline-flex" href="${detailsUrl}" title="View details"><span class="material-icons fs-6">info</span></a>`,
            ];
        });
        $('#projects').DataTable({
            data: dataSet,
            columns: [
                { title: "Name"},
                { title: "Archived?"},
                { title: "Visibility"},
                { title: "README" },
                { title: "LICENSE" },
                { title: "Last Fetch" },
                { title: "Last Activity" },
                { title: "Size (Mo)", className: "text-end" },
                { title: "Trivy", render: renderOrdered },
                { title: "Secrets", render: renderOrdered },
                {
                    title: 'Details',
                    orderable: false,
                    className: 'text-center'
                }
            ],
            paging: false,
            info: false
        });
    }).catch(function (error) {
        console.error(error);
        $('#projects').DataTable({
            data: [[
                `<div class="alert alert-danger mb-0" role="alert">fail to load repositories</div>`,
            ]],
            columns: [
                { title: "Error" },
            ],
            "paging": false,
            "info": false
        });
    });
}
