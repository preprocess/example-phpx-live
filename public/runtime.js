if (!window.PhpxLiveSocket) {
    const socket = new WebSocket("ws://127.0.0.1:8889/")

    socket.addEventListener("open", function() {
        document.querySelectorAll("[phpx-root]").forEach(root => {
            socket.send(
                JSON.stringify({
                    type: "phpx-root",
                    id: root.getAttribute("phpx-id"),
                    root: root.getAttribute("phpx-root")
                })
            )

            root.setAttribute("phpx-root", "")
        })

        const flushBinds = function() {
            document
                .querySelectorAll("[phpx-bind]")
                .forEach(elementWithBind => {
                    const root = elementWithBind.closest("[phpx-root]")

                    socket.send(
                        JSON.stringify({
                            type: "phpx-bind",
                            root: root.getAttribute("phpx-id"),
                            key: elementWithBind.getAttribute("phpx-bind"),
                            value: elementWithBind.value
                        })
                    )
                })
        }

        flushBinds()

        socket.addEventListener("message", function({ data }) {
            const json = JSON.parse(data)

            if (json.type === "phpx-render") {
                const root = document.querySelector(`[phpx-id='${json.root}']`)

                if (!root) {
                    throw new Error("Can't find the root")
                }

                root.innerHTML = json.data
                root.replaceWith(...root.childNodes)

                flushBinds()
            }
        })

        document.body.addEventListener("click", function(e) {
            if (e.target.hasAttribute("phpx-click")) {
                const [method, arguments] = e.target
                    .getAttribute("phpx-click")
                    .split(":")

                const root = e.target.closest("[phpx-root]")

                socket.send(
                    JSON.stringify({
                        type: "phpx-click",
                        method,
                        arguments,
                        root: root.getAttribute("phpx-id")
                    })
                )
            }
        })

        const flushBind = function(bind) {
            if (bind.hasAttribute("phpx-bind")) {
                const root = bind.closest("[phpx-root]")

                socket.send(
                    JSON.stringify({
                        type: "phpx-bind",
                        root: root.getAttribute("phpx-id"),
                        key: bind.getAttribute("phpx-bind"),
                        value: bind.value
                    })
                )
            }
        }

        document.body.addEventListener("change", function(e) {
            flushBind(e.target)
        })

        document.body.addEventListener("keydown", function(e) {
            flushBind(e.target)
        })

        document.body.addEventListener("keyup", function(e) {
            flushBind(e.target)
        })

        document.body.addEventListener("keypress", function(e) {
            if (e.target.hasAttribute("phpx-enter") && e.key === "Enter") {
                const [method, arguments] = e.target
                    .getAttribute("phpx-enter")
                    .split(":")

                const root = e.target.closest("[phpx-root]")

                socket.send(
                    JSON.stringify({
                        type: "phpx-enter",
                        method,
                        arguments,
                        root: root.getAttribute("phpx-id")
                    })
                )
            }
        })
    })

    window.PhpxLiveSocket = socket
}
