if (!window.PhpxLiveSocket) {
    const socket = new WebSocket("ws://127.0.0.1:8889/")

    socket.addEventListener("open", function() {
        const elementClasses = Array.prototype.slice
            .call(document.querySelectorAll("[phpx-class]"))
            .map(elementWithClass =>
                elementWithClass.getAttribute("phpx-class")
            )

        socket.send(
            JSON.stringify({
                type: "phpx-init",
                classes: Array.from(new Set(elementClasses))
            })
        )

        const flushBinds = function() {
            Array.prototype.slice
                .call(document.querySelectorAll("[phpx-bind]"))
                .forEach(elementWithBind => {
                    socket.send(
                        JSON.stringify({
                            type: "phpx-bind",
                            id: elementWithBind.getAttribute("phpx-id"),
                            class: elementWithBind.getAttribute("phpx-class"),
                            key: elementWithBind.getAttribute("phpx-bind"),
                            value: elementWithBind.value
                        })
                    )
                })
        }

        function findAncestor(element, selector) {
            while (
                (element = element.parentElement) &&
                !(element.matches || element.matchesSelector).call(
                    element,
                    selector
                )
            );

            return element
        }

        const findRootNode = function(id, cls) {
            return findAncestor(
                document.querySelector(`[phpx-id='${id}']`),
                "[phpx-class]"
            )
        }

        const removeExtraAttributes = function(root) {
            Array.prototype.slice
                .call(root.querySelectorAll("[phpx-class]"))
                .forEach(child => {
                    if (
                        child.hasAttribute("phpx-click") ||
                        child.hasAttribute("phpx-enter")
                    ) {
                        return
                    }

                    child.removeAttribute("phpx-class")
                })
        }

        const flushExtraAttributes = function() {
            Array.prototype.slice
                .call(document.querySelectorAll("[phpx-click], [phpx-enter]"))
                .forEach(elementWithInteraction => {
                    removeExtraAttributes(
                        findRootNode(
                            elementWithInteraction.getAttribute("phpx-id"),
                            elementWithInteraction.getAttribute("phpx-class")
                        )
                    )
                })
        }

        flushBinds()
        flushExtraAttributes()

        socket.addEventListener("message", function({ data }) {
            const json = JSON.parse(data)

            if (json.type === "phpx-render") {
                const root =
                    json.cause === "phpx-update"
                        ? document.querySelector(
                              `[phpx-marker='${json.marker}']`
                          )
                        : findRootNode(json.id, json.class)

                if (!root) {
                    throw new Exception(
                        `Can't find a root node to update: ${data}`
                    )
                }

                root.innerHTML = json.data
                root.replaceWith(...root.childNodes)

                flushBinds()
                flushExtraAttributes()
            }
        })

        document.body.addEventListener("click", function(e) {
            if (e.target.hasAttribute("phpx-click")) {
                const [method, arguments] = e.target
                    .getAttribute("phpx-click")
                    .split(":")

                socket.send(
                    JSON.stringify({
                        type: "phpx-click",
                        id: e.target.getAttribute("phpx-id"),
                        class: e.target.getAttribute("phpx-class"),
                        method,
                        arguments
                    })
                )
            }
        })

        const flushBind = function(bind) {
            if (bind.hasAttribute("phpx-bind")) {
                socket.send(
                    JSON.stringify({
                        type: "phpx-bind",
                        id: bind.getAttribute("phpx-id"),
                        class: bind.getAttribute("phpx-class"),
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

                socket.send(
                    JSON.stringify({
                        type: "phpx-enter",
                        id: e.target.getAttribute("phpx-id"),
                        class: e.target.getAttribute("phpx-class"),
                        method,
                        arguments
                    })
                )
            }
        })
    })

    window.PhpxLiveSocket = socket
}
