panel.chat is a client-server web application created as a capstone project during the Fall 2017 semester. It was developed as a team in collaboration with Philip Kocol and Trevor Schermerhorn.

## Objective
The primary objective of the capstone project was to gain experience engineering software as a team. In order to do this, we were given the opportunity to choose our own project out of ideas submitted by students. We chose to work with a project idea submitted by Phil, which other students voted as the number one idea.

This idea was to create an "open world" chat, which would allow users to chat with each-other in rooms divided into a two-dimensional grid of panels. The user could zoom in and out, and move around to view different conversations that would happen simultaneously.

We fulfilled the project requirements using HTML5, JavaScript, PHP, and WebSockets through the Ratchet library. I worked mostly on the front-end client and login system, while Trevor worked on the back-end server, and Phil helped out on both ends.

## Demonstration
A demonstration version of the application is available at [hentrope.github.io/panel.chat](https://hentrope.github.io/panel.chat/).

This version has been modified to function as a standalone client. As such, the client will be unable to send messages to or view messages from any other clients. Additionally, there is no login function, and it will treat users as a generic "OFFLINE" user.
