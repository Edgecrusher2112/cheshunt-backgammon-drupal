#!/bin/zsh

VPS_HOST="77.68.95.111"
VPS_USER="root"
LOCAL_PORT=3307
REMOTE_PORT=3306

echo "Opening SSH tunnel to $VPS_HOST ..."
ssh -N -L ${LOCAL_PORT}:127.0.0.1:${REMOTE_PORT} ${VPS_USER}@${VPS_HOST} &
TUNNEL_PID=$!

# Give the tunnel 2 seconds to initialize
sleep 2
