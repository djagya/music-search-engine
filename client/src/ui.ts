export const cx = (list: string[] | string, c2?: string | false | null, c3?: string | false | null): string => {
    return (Array.isArray(list) ? list : [list, c2, c3]).filter(c => !!c).join(' ');
};
