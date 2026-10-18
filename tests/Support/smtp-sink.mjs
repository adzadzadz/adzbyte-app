import net from "node:net";
import fs from "node:fs";

// Loopback SMTP receiver for disposable transport tests. No delivery/relay.
const [messageFile, readyFile] = process.argv.slice(2);
if (!messageFile || !readyFile) throw new Error("Expected test output files");
const server = net.createServer(socket => {
  socket.setEncoding("utf8");
  socket.write("220 localhost test SMTP\r\n");
  let buffer = "", body = "", data = false;
  socket.on("data", chunk => {
    buffer += chunk;
    let split;
    while ((split = buffer.indexOf("\r\n")) >= 0) {
      const line = buffer.slice(0, split);
      buffer = buffer.slice(split + 2);
      if (data) {
        if (line === ".") {
          fs.writeFileSync(messageFile, body, {mode: 0o600});
          data = false;
          socket.write("250 Message received\r\n");
        } else body += (line.startsWith("..") ? line.slice(1) : line) + "\r\n";
      } else if (/^(EHLO|HELO) /i.test(line)) socket.write("250 localhost\r\n");
      else if (/^(MAIL FROM:|RCPT TO:|RSET|NOOP)/i.test(line)) socket.write("250 OK\r\n");
      else if (line === "DATA") { data = true; body = ""; socket.write("354 End with dot\r\n"); }
      else if (line === "QUIT") { socket.end("221 Bye\r\n"); }
      else socket.write("502 Unsupported command\r\n");
    }
  });
  socket.on("error", () => {});
});
server.listen(0, "127.0.0.1", () => fs.writeFileSync(readyFile, String(server.address().port)));
