function getLastActivity(repository) {
    const dates = Object.keys(repository.activity);
    if (dates.length == 0) {
        return '0000-00-00';
    }
    const lastDate = dates[dates.length - 1];
    return `${lastDate.substring(0, 4)}-${lastDate.substring(4, 6)}-${lastDate.substring(6, 8)}`;
}


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
 * Load /api/repositories to #repositories tables.
 */
function loadRepositories() {
    fetch('/api/repositories').then(function (response) {
        if (response.status != 200) {
            throw new Error('fail to fetch repositories');
        }
        return response.json();
    }).then(function (items) {
        let dataSet = items.map(function (item) {
            const name = item.fullName;
            const sizeMo = (item.size / (1024 * 1024)).toFixed(1);
            return [
                `<a href="https://${name}">${name}</a>`,
                `<span class="${item.checks.readme ? "text-success" : "text-danger"}">${item.checks.readme ? "FOUND" : "MISSING"}</span>`,
                `<span class="${item.checks.license ? "text-success" : "text-danger"}">${item.checks.license ? item.checks.license : "MISSING"}</span>`,
                getLastActivity(item),
                sizeMo,
                item.checks.trivy
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
                            return trivy ? trivy.summary.CRITICAL + trivy.summary.HIGH : 0 ;
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
