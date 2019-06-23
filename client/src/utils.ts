export function formatTotal(total: any): string {
  return `${total.value}${total.relation === 'gte' ? '+' : ''}`;
}

export function formatDuration(duration: number): string {
  return `${Math.floor(duration / 60)}:${`0${Math.floor(duration % 60)}`.slice(-2)}`;
}
