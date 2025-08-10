export function gidEncode(gid) {
    if (!gid) return ''
    return btoa(gid).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '')
}

export function gidDecode(id64) {
    if (!id64) return ''
    const pad = '='.repeat((4 - (id64.length % 4)) % 4)
    const b64 = id64.replace(/-/g, '+').replace(/_/g, '/') + pad
    return atob(b64)
}

export function gidNumericId(gid) {
    if (!gid) return null
    const parts = gid.split('/')
    return parts[parts.length - 1] || null
}
