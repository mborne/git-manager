/**
 * Extract last activity date from project.metadata.activity
 * @param {object} project 
 * @returns 
 */
function getLastActivity(project) {
    const dates = Object.keys(project.metadata.activity);
    if (dates.length == 0) {
        return '0000-00-00';
    }
    const lastDate = dates[dates.length - 1];
    return `${lastDate.substring(0, 4)}-${lastDate.substring(4, 6)}-${lastDate.substring(6, 8)}`;
}

/**
 * Render trivy report.
 *
 * @param {?object} trivy 
 * @returns 
 */
function renderTrivy(trivy){
    if ( ! trivy ){
        return `<span class="text-warning">NO-DATA</span>`;
    }

    if ( ! trivy.success ){
        return `<span class="text-danger">FAILURE</span>`
    }
    return ['CRITICAL','HIGH'].map(severity => {
        const count = trivy.summary[severity];
        return `<span class="${count > 0 ? "text-danger" : "text-success"}">${severity}: ${count}`;
    }).join('<br />');
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
            return [
                `<a href="https://${name}">${name}</a>`,
                project.archived ? 'YES' : 'NO',
                project.visibility ? project.visibility : 'unknown',
                `<span class="${checks.readme ? "text-success" : "text-danger"}">${checks.readme ? "FOUND" : "MISSING"}</span>`,
                `<span class="${checks.license ? "text-success" : "text-danger"}">${checks.license ? checks.license : "MISSING"}</span>`,
                project.fetchedAt.split('T')[0],
                getLastActivity(project),
                sizeMo,
                checks.trivy
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
                { title: "Size (Mo)" },
                { 
                    title: "Trivy", 
                    render: function (trivy, type) {
                        if ( type === 'sort' || type === 'type' ) {
                            return trivy ? trivy.summary.CRITICAL + trivy.summary.HIGH : -1 ;
                        } else {
                            return renderTrivy(trivy);
                        }
                    }
                }
            ],
            "paging": false,
            "info": false
        });
    }).catch(function (error) {
        console.error(error);
        $('#projects').DataTable({
            data: [[
                `<span class="text-danger">fail to load repositories</span>`,
            ]],
            columns: [
                { title: "Error" },
            ],
            "paging": false,
            "info": false
        });
    });
}
