/** Stands in for @/echo so the harness never opens a Reverb socket. */
const channel = {
    listen: () => channel,
    stopListening: () => channel,
};

export default {
    private: () => channel,
    leave: () => {},
    connectionStatus: () => 'connected',
    socketId: () => 'preview-socket',
};
