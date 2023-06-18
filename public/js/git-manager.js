function loadRepositories() {
    $.getJSON('/api/repositories', function (items) {
        let dataSet = Object.keys(items).map(function(name){
            const item = items[name];
            console.log(item);
            return [
                name,
                `<span class="${item.readme ? "text-success" : "text-danger"}">${item.readme ? "FOUND" : "MISSING"}</span>`,
                `<span class="${item.license ? "text-success" : "text-danger"}">${item.license ? item.license : "MISSING"}</span>`,
                // `<a href="${result.repositoryUrl}" target="_blank">${result.username}</a>`,
                // `<a target="_blank" href="./data/${result.username}/tp-refactoring-graph.branches.txt">${result.branchName}</a>`,
                // `<a class="${result.build ? "text-success" : "text-danger"}" target="_blank" href="./data/${result.username}/tp-refactoring-graph.build.txt">${result.build ? "SUCCESS" : "FAILURE"}</a>`,
                // `<a class="${result.test ? "text-success" : "text-danger"}" target="_blank" href="./data/${result.username}/tp-refactoring-graph.build-test.txt">${result.test ? "SUCCESS" : "FAILURE"}</a>`
            ];
        });
        $('#repositories').DataTable({
            data: dataSet,
            columns: [
                { title: "Name" },
                { title: "README" },
                { title: "LICENSE" },
            ],
            "paging":   false,
            "info":     false
        });
    });
}
