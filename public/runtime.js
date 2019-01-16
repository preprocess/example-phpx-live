const socket = new WebSocket("ws://127.0.0.1:8889/")

socket.addEventListener("open", function() {
    const elementClasses = Array.prototype.slice
        .call(document.querySelectorAll("[phpx-class]"))
        .map(elementWithClass => elementWithClass.getAttribute("phpx-class"))

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

    flushBinds()

    socket.addEventListener("message", function({ data }) {
        const json = JSON.parse(data)

        if (json.type === "phpx-render") {
            let element = document.querySelector(`[phpx-id='${json.id}']`)

            while (
                element.parentNode &&
                element.parentNode.getAttribute("phpx-class") === json.class
            ) {
                element = element.parentNode
            }

            element.innerHTML = json.data

            flushBinds()

            if (json.cause) {
                document.querySelector(`[phpx-id='${json.cause}']`)
            }
        }
    })

    document.body.addEventListener("click", function(e) {
        if (e.target.hasAttribute("phpx-click")) {
            e.preventDefault()

            socket.send(
                JSON.stringify({
                    type: "phpx-click",
                    id: e.target.getAttribute("phpx-id"),
                    class: e.target.getAttribute("phpx-class"),
                    method: e.target.getAttribute("phpx-click")
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
            socket.send(
                JSON.stringify({
                    type: "phpx-enter",
                    id: e.target.getAttribute("phpx-id"),
                    class: e.target.getAttribute("phpx-class"),
                    method: e.target.getAttribute("phpx-enter")
                })
            )
        }
    })
})
