export function formatTotal(total) {
    return `${total.value}${total.relation === 'gte' ? '+' : ''}`;
}
