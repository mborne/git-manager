function getLastActivity(repository) {
    const dates = Object.keys(repository.activity);
    if (dates.length == 0) {
        return '0000-00-00';
    }
    const lastDate = dates[dates.length - 1];
    return `${lastDate.substring(0, 4)}-${lastDate.substring(4, 6)}-${lastDate.substring(6, 8)}`;
}


function renderTrivy(trivy){
    if ( ! trivy.success ){
        return `<span class="text-danger">FAILURE</span>`
    }
    return ['CRITICAL','HIGH'].map(severity => {
        const count = trivy.summary[severity];
        return `<span class="${count > 0 ? "text-danger" : "text-success"}">${severity}: ${count}`;
    }).join('<br />');
}

/**
 * Load /api/repositories to #repositories tables.
 */
function loadRepositories() {
    fetch('/api/projects').then(function (response) {
        if (response.status != 200) {
            throw new Error('fail to fetch projects');
        }
        return response.json();
    }).then(function (items) {
        let dataSet = items.map(function (item) {
            const name = item.name;
            const sizeMo = (item.size / (1024 * 1024)).toFixed(1);
            const checks = item.checks;
            return [
                `<a href="https://${name}">${name}</a>`,
                `<span class="${checks.readme ? "text-success" : "text-danger"}">${checks.readme ? "FOUND" : "MISSING"}</span>`,
                `<span class="${checks.license ? "text-success" : "text-danger"}">${checks.license ? checks.license : "MISSING"}</span>`,
                getLastActivity(item),
                sizeMo,
                checks.trivy
            ];
        });
        $('#repositories').DataTable({
            data: dataSet,
            columns: [
                { title: "Name"},
                { title: "README" },
                { title: "LICENSE" },
                { title: "Last Activity" },
                { title: "Size (Mo)" },
                { 
                    title: "Trivy", 
                    render: function (trivy, type) {
                        if ( type === 'sort' || type === 'type' ) {
                            return trivy.summary.CRITICAL + trivy.summary.HIGH;
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
        $('#repositories').DataTable({
            data: [[
                `<span class="text-danger">fail to load repositories (run 'bin/console git:stats')</span>`,
            ]],
            columns: [
                { title: "Error" },
            ],
            "paging": false,
            "info": false
        });
    });
}
